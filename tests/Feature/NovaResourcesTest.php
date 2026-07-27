<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Models\Permission as PermissionModel;
use BBSLab\NovaPermission\Models\Role as RoleModel;
use BBSLab\NovaPermission\Resources\Permission as PermissionResource;
use BBSLab\NovaPermission\Resources\Role as RoleResource;
use BBSLab\NovaPermission\Traits\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Http\Requests\NovaRequest;
use Spatie\Permission\PermissionRegistrar;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Nova\Post as PostResource;
use Workbench\App\Nova\Resource;
use Workbench\App\Nova\Service as ServiceResource;

/**
 * A model in the global namespace: policy auto-discovery finds nothing for it,
 * so the resource below has no authorization gate -> authorizable() === false.
 */
class NovaResPlainModel extends Model {}

/** A Nova resource with NO $permissionsForAbilities and NO policy. */
class NovaResPlainResource extends Workbench\App\Nova\Resource
{
    use Authorizable;

    public static $model = NovaResPlainModel::class;

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

/** A Nova resource that declares an explicit static $policy. */
class NovaResPolicyResource extends Workbench\App\Nova\Resource
{
    use Authorizable;

    public static $model = NovaResPlainModel::class;

    public static $policy = NovaResPlainModel::class; // any non-null value triggers the policy branch

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

function novaRes_request(?User $user): NovaRequest
{
    $request = NovaRequest::create('/');
    $request->setUserResolver(fn () => $user);

    return $request;
}

function novaRes_permissionModel(): Model
{
    $class = app(PermissionRegistrar::class)->getPermissionClass();

    return new $class;
}

function novaRes_roleModel(): Model
{
    $class = app(PermissionRegistrar::class)->getRoleClass();

    return new $class;
}

beforeEach(function (): void {
    Artisan::call(GenerateResourcePermissions::class);
});

/*
|--------------------------------------------------------------------------
| Traits/Authorizable (the Nova RESOURCE trait)
|--------------------------------------------------------------------------
*/

it('reports hasAbilities from the $permissionsForAbilities property', function (): void {
    expect(PostResource::hasAbilities())->toBeTrue()
        ->and(ServiceResource::hasAbilities())->toBeTrue()
        ->and(NovaResPlainResource::hasAbilities())->toBeFalse();
});

it('reports authorizable() from policy presence', function (): void {
    // Post/Service models have an auto-discovered policy.
    expect(PostResource::authorizable())->toBeTrue()
        ->and(ServiceResource::authorizable())->toBeTrue()
        // The plain model has no policy at all.
        ->and(NovaResPlainResource::authorizable())->toBeFalse();
});

it('builds a stable cache key with and without a resource', function (): void {
    $user = User::factory()->create();
    $request = novaRes_request($user);

    $anyKey = PostResource::cacheKey('viewAny', $request);
    expect($anyKey)->toBe('administrator:'.$user->getKey().':can:viewAny:'.Post::class);

    $post = Post::factory()->create();
    $viewKey = PostResource::cacheKey('view', $request, $post);
    expect($viewKey)->toBe('administrator:'.$user->getKey().':can:view:'.Post::class.':'.$post->getKey());
});

it('falls back to "unauthenticated" in the cache key when no user is resolved', function (): void {
    $key = PostResource::cacheKey('viewAny', novaRes_request(null));

    expect($key)->toBe('administrator:unauthenticated:can:viewAny:'.Post::class);
});

it('authorizes viewAny when the user holds the general permission', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->givePermissionTo('viewAny post');

    expect(PostResource::authorizedToViewAny(novaRes_request($user)))->toBeTrue();
});

it('denies viewAny when the user lacks the permission', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(PostResource::authorizedToViewAny(novaRes_request($user)))->toBeFalse();
});

it('allows viewAny for a resource that is not authorizable', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    // No policy -> authorizable() false -> the early "return true" branch.
    expect(NovaResPlainResource::authorizedToViewAny(novaRes_request($user)))->toBeTrue();
});

it('authorizes create when the user holds the permission', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->givePermissionTo('create post');

    expect(PostResource::authorizedToCreate(novaRes_request($user)))->toBeTrue();
});

it('denies create when the user lacks the permission', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(PostResource::authorizedToCreate(novaRes_request($user)))->toBeFalse();
});

it('allows create for a resource that is not authorizable', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(NovaResPlainResource::authorizedToCreate(novaRes_request($user)))->toBeTrue();
});

it('authorizes an instance ability when the user holds the permission', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->givePermissionTo('view post');

    $resource = new PostResource(Post::factory()->create());

    expect($resource->authorizedTo(novaRes_request($user), 'view'))->toBeTrue();
});

it('denies an instance ability when the user lacks the permission', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $resource = new PostResource(Post::factory()->create());

    expect($resource->authorizedTo(novaRes_request($user), 'view'))->toBeFalse();
});

it('memoizes the gate result when the cache is enabled', function (): void {
    config(['nova-permission.cache.enabled' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);
    $request = novaRes_request($user);

    // First call (no permission) computes and caches "false".
    expect(PostResource::authorizedToViewAny($request))->toBeFalse();

    // Grant the permission and clear spatie's own cache so the only remaining
    // source of staleness is the package's PermissionCache.
    $user->givePermissionTo('viewAny post');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Still false: served from the memoized entry, proving the cached path ran.
    expect(PostResource::authorizedToViewAny($request))->toBeFalse();
});

it('resolves authorization against the resource itself when it declares an explicit policy', function (): void {
    // A resource with a static $policy authorizes against the resource (so its
    // policy applies); without one it falls back to the model. Mirrors Nova 5's
    // Util::resolveResourceOrModelForAuthorization, which does not exist in Nova 4.
    $withPolicy = new NovaResPolicyResource(new NovaResPlainModel);
    $method = new ReflectionMethod(NovaResPolicyResource::class, 'resolveForAuthorization');
    $method->setAccessible(true);

    expect($method->invoke(null, $withPolicy))->toBe($withPolicy);
});

it('denies an instance ability when the model resolves to no Nova resource', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    // The plain model is registered with no Nova resource, so
    // Nova::resourceForModel() returns null and authorizedTo() bails out false.
    $resource = new NovaResPlainResource(new NovaResPlainModel);

    expect($resource->authorizedTo(novaRes_request($user), 'view'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Resources/Permission and Resources/Role (fields + static config)
|--------------------------------------------------------------------------
*/

it('builds the Permission resource fields', function (): void {
    $resource = new PermissionResource(novaRes_permissionModel());

    $fields = $resource->fields(novaRes_request(null));

    expect($fields)->toBeArray()->not->toBeEmpty()
        ->and(collect($fields)->pluck('attribute'))->toContain('name', 'group', 'guard_name');
});

it('adds the authorizable MorphTo field only when authorizable_models is configured', function (): void {
    $resource = new PermissionResource(novaRes_permissionModel());
    $request = novaRes_request(null);

    $baseline = count($resource->fields($request));

    config(['nova-permission.authorizable_models' => [PostResource::class]]);

    expect(count($resource->fields($request)))->toBe($baseline + 1);
});

it('builds the Role resource fields', function (): void {
    $resource = new RoleResource(novaRes_roleModel());

    $fields = $resource->fields(novaRes_request(null));

    expect($fields)->toBeArray()->not->toBeEmpty()
        ->and(collect($fields)->pluck('attribute'))->toContain('name', 'guard_name', 'override_permission');
});

it('renders the guard name as the resource subtitle', function (): void {
    $permission = novaRes_permissionModel();
    $permission->guard_name = 'web';
    expect((new PermissionResource($permission))->subtitle())->toBe('Guard: web');

    $role = novaRes_roleModel();
    $role->guard_name = 'api';
    expect((new RoleResource($role))->subtitle())->toBe('Guard: api');
});

it('applies the canSee callback to the override permission field', function (): void {
    RoleResource::canSeeOverridePermmission(fn () => true);

    try {
        $fields = (new RoleResource(novaRes_roleModel()))->fields(novaRes_request(null));

        expect($fields)->not->toBeEmpty();
    } finally {
        // Reset the leaked static so other tests keep the default behaviour.
        RoleResource::canSeeOverridePermmission(null);
    }
});

/*
|--------------------------------------------------------------------------
| Traits/HasFieldName
|--------------------------------------------------------------------------
*/

it('translates a field name from a validation attribute when one exists', function (): void {
    app('translator')->addLines(['validation.attributes.name' => 'nom'], 'en');

    $resource = new PermissionResource(novaRes_permissionModel());
    $method = new ReflectionMethod($resource, 'getTranslatedFieldName');

    expect($method->invoke($resource, 'Name'))->toBe('Nom');
});

it('humanizes a field name when no validation attribute is defined', function (): void {
    $resource = new PermissionResource(novaRes_permissionModel());
    $method = new ReflectionMethod($resource, 'getTranslatedFieldName');

    expect($method->invoke($resource, 'Some Field'))->toBe('Some Field');
});

/*
|--------------------------------------------------------------------------
| Policies/PermissionPolicy and Policies/RolePolicy (via the Gate)
|--------------------------------------------------------------------------
*/

dataset('permission policy abilities', [
    'viewAny' => ['viewAny', 'viewAny permission', false],
    'view' => ['view', 'view permission', true],
    'create' => ['create', 'create permission', false],
    'update' => ['update', 'update permission', true],
    'delete' => ['delete', 'delete permission', true],
]);

it('grants a Permission policy ability to a user holding the permission', function (string $ability, string $permission, bool $needsModel): void {
    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    $target = $needsModel
        ? PermissionModel::query()->where('name', 'view permission')->firstOrFail()
        : PermissionModel::class;

    expect(Gate::forUser($user)->check($ability, $target))->toBeTrue();
})->with('permission policy abilities');

it('denies a Permission policy ability to a user without the permission', function (string $ability, string $permission, bool $needsModel): void {
    $user = User::factory()->create();

    $target = $needsModel
        ? PermissionModel::query()->where('name', 'view permission')->firstOrFail()
        : PermissionModel::class;

    expect(Gate::forUser($user)->check($ability, $target))->toBeFalse();
})->with('permission policy abilities');

dataset('role policy abilities', [
    'viewAny' => ['viewAny', 'viewAny role', false],
    'view' => ['view', 'view role', true],
    'create' => ['create', 'create role', false],
    'update' => ['update', 'update role', true],
    'delete' => ['delete', 'delete role', true],
]);

it('grants a Role policy ability to a user holding the permission', function (string $ability, string $permission, bool $needsModel): void {
    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    $target = $needsModel
        ? RoleModel::query()->create(['name' => 'target-role', 'guard_name' => 'web'])
        : RoleModel::class;

    expect(Gate::forUser($user)->check($ability, $target))->toBeTrue();
})->with('role policy abilities');

it('denies a Role policy ability to a user without the permission', function (string $ability, string $permission, bool $needsModel): void {
    $user = User::factory()->create();

    $target = $needsModel
        ? RoleModel::query()->create(['name' => 'target-role', 'guard_name' => 'web'])
        : RoleModel::class;

    expect(Gate::forUser($user)->check($ability, $target))->toBeFalse();
})->with('role policy abilities');

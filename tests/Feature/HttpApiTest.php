<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Actions\GenerateResourcePermissionsAction;
use BBSLab\NovaPermission\Console\Commands\GenerateResourcePermissions;
use BBSLab\NovaPermission\Http\Controllers\PermissionController;
use BBSLab\NovaPermission\Http\Controllers\ToolController;
use BBSLab\NovaPermission\Http\Middleware\Authorize;
use BBSLab\NovaPermission\Http\Requests\AttachRequest;
use BBSLab\NovaPermission\Http\Requests\PermissionByAuthorizableRequest;
use BBSLab\NovaPermission\Http\Requests\PermissionByGroupRequest;
use BBSLab\NovaPermission\Models\Permission;
use BBSLab\NovaPermission\Models\Role;
use BBSLab\NovaPermission\PermissionBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Inertia\Response;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Workbench\App\Models\Post;

beforeEach(function (): void {
    // In this harness the workbench config/permission.php is not loaded, so the
    // registrar defaults to Spatie's base models. Point it at the package models
    // (what a real app configures) so the controllers resolve the right classes.
    app(PermissionRegistrar::class)->setPermissionClass(Permission::class);
    app(PermissionRegistrar::class)->setRoleClass(Role::class);

    Artisan::call(GenerateResourcePermissions::class);
});

/** Read a JsonResponse payload as an array. */
function httpApi_json(JsonResponse $response): array
{
    return $response->getData(true);
}

/** Build a scoped (per-model) permission on a Post instance. */
function httpApi_scopedPermission(Post $post, string $name = 'view post'): Permission
{
    return Permission::query()->create([
        'name' => $name,
        'guard_name' => 'web',
        'authorizable_type' => $post->getMorphClass(),
        'authorizable_id' => $post->getKey(),
    ]);
}

// ---------------------------------------------------------------------------
// PermissionController::groups
// ---------------------------------------------------------------------------

it('lists roles and simple groups, excluding override roles', function (): void {
    $normal = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);
    Role::query()->create(['name' => 'super', 'guard_name' => 'web', 'override_permission' => true]);

    $response = app(PermissionController::class)->groups(Request::create('/'));
    $data = httpApi_json($response);

    $roleNames = collect($data['roles'])->pluck('name');
    expect($roleNames)->toContain('editor')
        ->and($roleNames)->not->toContain('super');

    $groups = collect($data['groups'])->pluck('group');
    expect($groups)->toContain('Post')
        ->and($groups)->toContain('Service')
        ->and($groups)->toContain('User');
});

it('includes model groups with a human display label', function (): void {
    $post = Post::factory()->create(['title' => 'Hello']);
    httpApi_scopedPermission($post);

    $response = app(PermissionController::class)->groups(Request::create('/'));
    $data = httpApi_json($response);

    $modelGroup = collect($data['groups'])->first(fn ($g) => ! empty($g['authorizable_id']));

    expect($modelGroup)->not->toBeNull()
        ->and($modelGroup['display'])->toBe('Post: Hello')
        ->and($modelGroup)->not->toHaveKey('authorizable');
});

it('skips a scoped permission whose authorizable was deleted', function (): void {
    $post = Post::factory()->create();
    httpApi_scopedPermission($post, 'view post');
    $post->delete(); // orphan the scoped permission (authorizable_id now points nowhere)

    // Must not throw; the orphaned model group is filtered out instead of 500ing.
    $data = httpApi_json(app(PermissionController::class)->groups(Request::create('/')));

    expect(collect($data['groups'])->first(fn ($g) => ! empty($g['authorizable_id'])))->toBeNull();
});

it('filters groups by the search term', function (): void {
    $response = app(PermissionController::class)->groups(Request::create('/?search=post'));
    $data = httpApi_json($response);

    $groups = collect($data['groups'])->pluck('group');
    expect($groups)->toContain('Post')
        ->and($groups)->not->toContain('Service');
});

// ---------------------------------------------------------------------------
// PermissionController::permissionsByGroup
// ---------------------------------------------------------------------------

it('returns the permissions of a group with per-role toggles', function (): void {
    $role = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);
    $role->givePermissionTo('view post');

    $request = PermissionByGroupRequest::create('/', 'POST', ['guard' => 'web', 'group' => 'Post']);
    $data = httpApi_json(app(PermissionController::class)->permissionsByGroup($request));

    expect($data)->toHaveCount(8); // the 8 Post abilities

    $viewPost = collect($data)->firstWhere('name', 'view post');
    expect($viewPost['roles'][$role->getKey()])->toBeTrue();
});

it('applies the search filter within a group', function (): void {
    $request = PermissionByGroupRequest::create('/?search=view', 'POST', ['guard' => 'web', 'group' => 'Post']);
    $data = httpApi_json(app(PermissionController::class)->permissionsByGroup($request));

    // "view post" and "viewAny post"
    expect($data)->toHaveCount(2)
        ->and(collect($data)->pluck('name')->all())->toEqualCanonicalizing(['view post', 'viewAny post']);
});

it('returns the ungrouped permissions when no group is supplied', function (): void {
    Permission::query()->create(['name' => 'ungrouped ability', 'guard_name' => 'web']);

    $request = PermissionByGroupRequest::create('/', 'POST', ['guard' => 'web']);
    $data = httpApi_json(app(PermissionController::class)->permissionsByGroup($request));

    $names = collect($data)->pluck('name');
    expect($names)->toContain('ungrouped ability')
        ->and($names)->not->toContain('view post'); // grouped permission excluded
});

it('filters permissionsByGroup by guard', function (): void {
    // Only the "web" guard exists, so an "api" guard yields nothing.
    $request = PermissionByGroupRequest::create('/', 'POST', ['guard' => 'api', 'group' => 'Post']);
    $data = httpApi_json(app(PermissionController::class)->permissionsByGroup($request));

    expect($data)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// PermissionController::permissionsByAuthorizable
// ---------------------------------------------------------------------------

it('returns only the permissions scoped to a given authorizable', function (): void {
    $post = Post::factory()->create();
    $scoped = httpApi_scopedPermission($post, 'view post');
    httpApi_scopedPermission(Post::factory()->create(), 'view post'); // another post, must not leak

    $request = PermissionByAuthorizableRequest::create('/', 'POST', [
        'guard' => 'web',
        'id' => $post->getKey(),
        'type' => $post->getMorphClass(),
    ]);
    $data = httpApi_json(app(PermissionController::class)->permissionsByAuthorizable($request));

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($scoped->getKey())
        ->and($data[0]['name'])->toBe('view post')
        ->and($data[0])->toHaveKey('roles');
});

// ---------------------------------------------------------------------------
// PermissionController::attachPermission (attach + detach branches)
// ---------------------------------------------------------------------------

it('attaches permissions to a role', function (): void {
    $role = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);
    $ids = Permission::query()->where('group', 'Post')->pluck('id')->take(2)->all();

    $request = AttachRequest::create('/', 'POST', ['permissions' => $ids, 'attach' => true]);
    $data = httpApi_json(app(PermissionController::class)->attachPermission($request, $role->getKey()));

    expect($role->permissions()->pluck('id')->all())->toEqualCanonicalizing($ids)
        ->and($data['message'])->toContain('associated');
});

it('detaches permissions from a role', function (): void {
    $role = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);
    $ids = Permission::query()->where('group', 'Post')->pluck('id')->take(2)->all();
    $role->permissions()->sync($ids);

    $request = AttachRequest::create('/', 'POST', ['permissions' => $ids, 'attach' => false]);
    $data = httpApi_json(app(PermissionController::class)->attachPermission($request, $role->getKey()));

    expect($role->permissions()->count())->toBe(0)
        ->and($data['message'])->toContain('detached');
});

it('404s when attaching to an unknown role', function (): void {
    $request = AttachRequest::create('/', 'POST', ['permissions' => [1], 'attach' => true]);

    expect(fn () => app(PermissionController::class)->attachPermission($request, 999999))
        ->toThrow(ModelNotFoundException::class);
});

// ---------------------------------------------------------------------------
// PermissionController::generatePermission
// ---------------------------------------------------------------------------

it('generates resource permissions and returns a message', function (): void {
    Permission::query()->delete();
    expect(Permission::query()->count())->toBe(0);

    $response = app(PermissionController::class)->generatePermission(
        app(GenerateResourcePermissionsAction::class)
    );
    $data = httpApi_json($response);

    expect(Permission::query()->where('name', 'view post')->exists())->toBeTrue()
        ->and($data['message'])->toBe('Permissions successfully generated');
});

it('aborts 500 when the generation action throws', function (): void {
    $action = new class extends GenerateResourcePermissionsAction
    {
        public function execute(): void
        {
            throw new RuntimeException('boom');
        }
    };

    expect(fn () => app(PermissionController::class)->generatePermission($action))
        ->toThrow(HttpException::class, 'boom');
});

// ---------------------------------------------------------------------------
// ToolController
// ---------------------------------------------------------------------------

it('renders the PermissionBuilder inertia page', function (): void {
    $response = (new ToolController)(NovaRequest::create('/', 'GET'));

    expect($response)->toBeInstanceOf(Response::class);

    $component = new ReflectionProperty($response, 'component');
    $component->setAccessible(true);
    expect($component->getValue($response))->toBe('PermissionBuilder');
});

// ---------------------------------------------------------------------------
// Authorize middleware
// ---------------------------------------------------------------------------

it('matchesTool only for PermissionBuilder tools', function (): void {
    $middleware = new Authorize;
    $other = new class extends Tool
    {
        public function menu(Request $request)
        {
            return null;
        }
    };

    expect($middleware->matchesTool(new PermissionBuilder))->toBeTrue()
        ->and($middleware->matchesTool($other))->toBeFalse();
});

it('calls next when the PermissionBuilder tool authorizes the request', function (): void {
    Nova::$tools = [];
    Nova::tools([(new PermissionBuilder)->canSee(fn () => true)]);

    $called = false;
    $result = (new Authorize)->handle(Request::create('/'), function ($request) use (&$called) {
        $called = true;

        return new Illuminate\Http\Response('NEXT');
    });

    expect($called)->toBeTrue()->and($result->getContent())->toBe('NEXT');
});

it('aborts 403 when the PermissionBuilder tool denies the request', function (): void {
    Nova::$tools = [];
    Nova::tools([(new PermissionBuilder)->canSee(fn () => false)]);

    try {
        (new Authorize)->handle(Request::create('/'), fn ($request) => 'NEXT');
        expect(false)->toBeTrue('expected an HttpException');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('aborts 403 when no PermissionBuilder tool is registered', function (): void {
    Nova::$tools = [];

    try {
        (new Authorize)->handle(Request::create('/'), fn ($request) => 'NEXT');
        expect(false)->toBeTrue('expected an HttpException');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

// ---------------------------------------------------------------------------
// FormRequest validation rules
// ---------------------------------------------------------------------------

it('authorizes the form requests', function (): void {
    expect((new PermissionByGroupRequest)->authorize())->toBeTrue()
        ->and((new PermissionByAuthorizableRequest)->authorize())->toBeTrue()
        ->and((new AttachRequest)->authorize())->toBeTrue();
});

it('trims the search value from the query string', function (): void {
    expect(PermissionByGroupRequest::create('/?search=%20%20hello%20%20', 'POST')->searchValue())->toBe('hello')
        ->and(PermissionByGroupRequest::create('/', 'POST')->searchValue())->toBe('');
});

it('validates PermissionByGroupRequest rules', function (array $data, bool $valid): void {
    expect(Validator::make($data, (new PermissionByGroupRequest)->rules())->passes())->toBe($valid);
})->with([
    'valid guard + existing group' => [['guard' => 'web', 'group' => 'Post'], true],
    'valid guard, no group (nullable)' => [['guard' => 'web'], true],
    'unknown guard' => [['guard' => 'api', 'group' => 'Post'], false],
    'missing guard' => [['group' => 'Post'], false],
    'non-existent group' => [['guard' => 'web', 'group' => 'Nope'], false],
]);

it('validates PermissionByAuthorizableRequest rules', function (array $data, bool $valid): void {
    expect(Validator::make($data, (new PermissionByAuthorizableRequest)->rules())->passes())->toBe($valid);
})->with([
    'valid class type' => [['guard' => 'web', 'id' => 1, 'type' => Post::class], true],
    'unknown class type' => [['guard' => 'web', 'id' => 1, 'type' => 'Not\\A\\Class'], false],
    'missing id' => [['guard' => 'web', 'type' => Post::class], false],
    'missing type' => [['guard' => 'web', 'id' => 1], false],
]);

it('validates AttachRequest rules', function (): void {
    $id = Permission::query()->value('id');
    $rules = (new AttachRequest)->rules();

    expect(Validator::make(['permissions' => [$id], 'attach' => true], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['permissions' => [$id], 'attach' => false], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['permissions' => [999999], 'attach' => true], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['permissions' => [$id], 'attach' => 'notbool'], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['permissions' => 'notarray', 'attach' => true], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['attach' => true], $rules)->passes())->toBeFalse();
});

<?php

declare(strict_types=1);

use BBSLab\NovaPermission\Actions\GenerateResourcePermissionsAction;
use BBSLab\NovaPermission\Contracts\HasAbilities;
use BBSLab\NovaPermission\Contracts\Role as RoleContract;
use BBSLab\NovaPermission\Models\Role;
use BBSLab\NovaPermission\NovaPermissionServiceProvider;
use BBSLab\NovaPermission\PermissionBuilder;
use BBSLab\NovaPermission\Policies\RolePolicy;
use BBSLab\NovaPermission\Resources\Permission as PermissionResource;
use BBSLab\NovaPermission\Resources\Role as RoleResource;
use BBSLab\NovaPermission\Traits\Authorizable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource as NovaResource;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Workbench\App\Models\User;

/*
 * Dummy Nova resources used to drive the "no abilities" / "not authorizable"
 * branches. Their $model values are never instantiated: these branches only
 * do class-string comparisons (Nova::resourceForModel) or bail out before
 * newResource() (Nova::resourceInstanceForKey on an unregistered key).
 */
class ToolProvUnregisteredResource extends NovaResource
{
    public static $model = 'ToolProv\\Unregistered';

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

class ToolProvPlainResource extends NovaResource
{
    public static $model = 'ToolProv\\PlainModel';

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

class ToolProvEmptyAbilitiesResource extends NovaResource implements HasAbilities
{
    use Authorizable;

    public static $permissionsForAbilities = [];

    public static $model = 'ToolProv\\EmptyModel';

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}

// ---------------------------------------------------------------------------
// PermissionBuilder (the Nova Tool)
// ---------------------------------------------------------------------------

it('boots the tool and authorizes by default', function (): void {
    $tool = PermissionBuilder::make();

    // boot() registers the script/style and merges the package translations
    // into Nova; it must run without error.
    $tool->boot();

    expect($tool)->toBeInstanceOf(PermissionBuilder::class)
        ->and($tool->authorize(Request::create('/')))->toBeTrue();
});

it('builds a menu section pointing at the tool', function (): void {
    $menu = PermissionBuilder::make()->menu(Request::create('/'));

    expect($menu)->toBeInstanceOf(MenuSection::class)
        ->and($menu->component)->toBe('menu-section')
        ->and($menu->jsonSerialize())->toBeArray();
});

// ---------------------------------------------------------------------------
// NovaPermissionServiceProvider — Gate::after override hook
// ---------------------------------------------------------------------------

describe('Gate::after override hook', function (): void {
    // A gate ability that never decides on its own, so the result is dictated
    // solely by the provider's Gate::after callback.
    beforeEach(function (): void {
        Gate::define('toolProv_afterProbe', fn ($user) => null);
    });

    it('grants any ability to a user holding an override role', function (): void {
        $role = Role::query()->create(['name' => 'super', 'guard_name' => 'web', 'override_permission' => true]);
        $user = User::factory()->create();
        $user->assignRole($role);

        expect(Gate::forUser($user)->check('toolProv_afterProbe'))->toBeTrue();
    });

    it('does not grant a CanOverridePermission user without an override role', function (): void {
        $user = User::factory()->create();

        expect(Gate::forUser($user)->check('toolProv_afterProbe'))->toBeFalse();
    });

    it('ignores a user that is not a CanOverridePermission instance', function (): void {
        $plain = new class implements Illuminate\Contracts\Auth\Access\Authorizable
        {
            use Illuminate\Foundation\Auth\Access\Authorizable;
        };

        expect(Gate::forUser($plain)->check('toolProv_afterProbe'))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// NovaPermissionServiceProvider — resource + route registration
// ---------------------------------------------------------------------------

it('registers the package resources and binds their models', function (): void {
    $registrar = app(PermissionRegistrar::class);

    expect(PermissionResource::$model)->toBe($registrar->getPermissionClass())
        ->and(RoleResource::$model)->toBe($registrar->getRoleClass())
        ->and(Nova::$resources)->toContain(PermissionResource::class)
        ->and(Nova::$resources)->toContain(RoleResource::class);
});

it('registers the tool api and inertia routes', function (): void {
    $uris = collect(Route::getRoutes()->getRoutes())->map->uri();

    expect($uris->contains(fn (string $uri): bool => str_contains($uri, 'nova-vendor/nova-permission')))->toBeTrue();
});

it('skips route registration when the routes are cached', function (): void {
    $before = Route::getRoutes()->count();

    $app = Mockery::mock(Application::class);
    $app->shouldReceive('routesAreCached')->andReturn(true);

    $provider = new NovaPermissionServiceProvider($app);
    $method = new ReflectionMethod($provider, 'routes');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(Route::getRoutes()->count())->toBe($before);
});

// ---------------------------------------------------------------------------
// Models\Role
// ---------------------------------------------------------------------------

it('is a spatie role implementing the package role contract', function (): void {
    expect(new Role)
        ->toBeInstanceOf(SpatieRole::class)
        ->toBeInstanceOf(RoleContract::class);
});

// ---------------------------------------------------------------------------
// Traits\HasRoles — canOverridePermission() false path
// ---------------------------------------------------------------------------

it('reports no override for a user without an override role', function (): void {
    expect(User::factory()->create()->canOverridePermission())->toBeFalse();
});

// ---------------------------------------------------------------------------
// GenerateResourcePermissionsAction — a non-authorizable resource is skipped
// ---------------------------------------------------------------------------

it('treats a resource without the HasAbilities contract as having no abilities', function (): void {
    $action = new GenerateResourcePermissionsAction;
    $method = new ReflectionMethod($action, 'resourceHasAbilities');
    $method->setAccessible(true);

    // Unregistered → Nova::resourceInstanceForKey() returns null → false.
    expect($method->invoke($action, ToolProvUnregisteredResource::class))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Policy::getPermissionFromResource — the three null branches
// ---------------------------------------------------------------------------

describe('Policy::getPermissionFromResource', function (): void {
    beforeEach(function (): void {
        $this->originalResources = Nova::$resources;
        $this->originalByModel = Nova::$resourcesByModel;

        Nova::resources([
            ToolProvPlainResource::class,
            ToolProvEmptyAbilitiesResource::class,
        ]);

        $policy = new RolePolicy;
        $this->resolve = function (string $permission, string $model) use ($policy): ?string {
            $method = new ReflectionMethod($policy, 'getPermissionFromResource');
            $method->setAccessible(true);

            return $method->invoke($policy, $permission, $model);
        };
    });

    afterEach(function (): void {
        Nova::$resources = $this->originalResources;
        Nova::$resourcesByModel = $this->originalByModel;
    });

    it('returns null when no resource is registered for the model', function (): void {
        expect(($this->resolve)('view', 'ToolProv\\Missing'))->toBeNull();
    });

    it('returns null when the resource does not implement HasAbilities', function (): void {
        expect(($this->resolve)('view', 'ToolProv\\PlainModel'))->toBeNull();
    });

    it('returns null when the resource reports no abilities', function (): void {
        expect(($this->resolve)('view', 'ToolProv\\EmptyModel'))->toBeNull();
    });
});

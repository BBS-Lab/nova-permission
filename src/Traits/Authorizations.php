<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Traits;

use BBSLab\NovaPermission\Contracts\CanOverridePermission;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

trait Authorizations
{
    /**
     * Permissions scoped to this specific model instance ("granular" permissions).
     *
     * @return MorphMany<Model, Model>
     */
    public function authorizations(): MorphMany
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Model> $permissionClass */
        $permissionClass = $registrar->getPermissionClass();

        /** @var MorphMany<Model, Model> $relation */
        $relation = $this->morphMany($permissionClass, 'authorizable');

        return $relation;
    }

    /**
     * Scope the query to the instances the given user may access for $permission,
     * mirroring the policy's "instance-override" semantics:
     *
     *  - an instance WITHOUT a scoped $permission is visible when the user holds
     *    the general (unscoped) $permission;
     *  - an instance WITH a scoped $permission is visible only when the user holds
     *    that instance permission (directly or through a role);
     *  - a user with an "override" role sees everything.
     *
     * @param  EloquentBuilder<Model>  $query
     * @return EloquentBuilder<Model>
     */
    public function scopeAuthorize(EloquentBuilder $query, Model $user, string $permission): EloquentBuilder
    {
        if ($user instanceof CanOverridePermission && $user->canOverridePermission()) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $morphType = $query->getModel()->getMorphClass();
        $guard = config('nova.guard') ?? config('auth.defaults.guard');

        $permissionsTable = config('permission.table_names.permissions');
        $modelHasPermissions = config('permission.table_names.model_has_permissions');
        $roleHasPermissions = config('permission.table_names.role_has_permissions');
        $modelHasRoles = config('permission.table_names.model_has_roles');

        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        $generalPermission = $permissionClass::query()
            ->where('name', '=', $permission)
            ->where('guard_name', '=', $guard)
            ->whereNull('authorizable_id')
            ->whereNull('authorizable_type')
            ->first();

        // $user is a consumer model using Spatie's HasRoles trait; the package
        // cannot name a concrete class, so hasPermissionTo() is resolved at runtime.
        // @phpstan-ignore method.notFound
        $hasGeneral = $generalPermission !== null && $user->hasPermissionTo($generalPermission);

        return $query->where(function (EloquentBuilder $query) use (
            $user, $table, $morphType, $guard, $hasGeneral,
            $permissionsTable, $modelHasPermissions, $roleHasPermissions, $modelHasRoles, $permission
        ) {
            // Instances carrying a scoped permission the user actually holds.
            $query->whereExists(function (QueryBuilder $sub) use (
                $user, $table, $morphType, $guard, $permission,
                $permissionsTable, $modelHasPermissions, $roleHasPermissions, $modelHasRoles
            ) {
                $sub->select(DB::raw(1))
                    ->from("{$permissionsTable} as p")
                    ->whereColumn('p.authorizable_id', "{$table}.id")
                    ->where('p.authorizable_type', '=', $morphType)
                    ->where('p.name', '=', $permission)
                    ->where('p.guard_name', '=', $guard)
                    ->where(function (QueryBuilder $holds) use (
                        $user, $modelHasPermissions, $roleHasPermissions, $modelHasRoles
                    ) {
                        $holds->whereExists(function (QueryBuilder $direct) use ($user, $modelHasPermissions) {
                            $direct->select(DB::raw(1))
                                ->from("{$modelHasPermissions} as mhp")
                                ->whereColumn('mhp.permission_id', '=', 'p.id')
                                ->where('mhp.model_type', '=', $user->getMorphClass())
                                ->where('mhp.model_id', '=', $user->getKey());
                        })->orWhereExists(function (QueryBuilder $viaRole) use (
                            $user, $roleHasPermissions, $modelHasRoles
                        ) {
                            $viaRole->select(DB::raw(1))
                                ->from("{$roleHasPermissions} as rhp")
                                ->whereColumn('rhp.permission_id', '=', 'p.id')
                                ->join("{$modelHasRoles} as mhr", function (JoinClause $join) use ($user) {
                                    $join->on('rhp.role_id', '=', 'mhr.role_id')
                                        ->where('mhr.model_type', '=', $user->getMorphClass())
                                        ->where('mhr.model_id', '=', $user->getKey());
                                });
                        });
                    });
            });

            // Instances with no scoped permission fall back to the general permission.
            if ($hasGeneral) {
                $query->orWhereNotExists(function (QueryBuilder $sub) use (
                    $table, $morphType, $guard, $permission, $permissionsTable
                ) {
                    $sub->select(DB::raw(1))
                        ->from("{$permissionsTable} as pg")
                        ->whereColumn('pg.authorizable_id', "{$table}.id")
                        ->where('pg.authorizable_type', '=', $morphType)
                        ->where('pg.name', '=', $permission)
                        ->where('pg.guard_name', '=', $guard);
                });
            }
        });
    }
}

<?php

namespace BBSLab\NovaPermission\Traits;

use Avec\Models\Chamber as ChamberModel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

trait Authorizations
{
    public function authorizations(): MorphMany
    {
        /** @var \Spatie\Permission\PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);

        return $this->morphMany(get_class($registrar->getPermissionClass()), 'authorizable');
    }

    /**
     * Scope the query to entries the user is authorized to retrieve.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAuthorize(EloquentBuilder $query, $user): EloquentBuilder
    {
        $permissionsTable = config('permission.table_names.permissions');
        $modeHasPermissionsTable = config('permission.table_names.model_has_permissions');
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions');
        $modelHasRolesTable = config('permission.table_names.model_has_roles');

        return $query->join("{$permissionsTable} as p", function (JoinClause $join) use (
            $user, $modeHasPermissionsTable, $roleHasPermissionsTable, $modelHasRolesTable
        ) {
            $join->on('p.authorizable_id', '=', 'chambers.id')
                ->where('p.authorizable_type', '=', static::class)
                ->where(function (JoinClause $query) use (
                    $user, $modeHasPermissionsTable, $roleHasPermissionsTable, $modelHasRolesTable
                ) {
                    $query->whereExists(function (QueryBuilder $query) use ($user, $modeHasPermissionsTable) {
                        $query->select(DB::raw(1))
                            ->from("$modeHasPermissionsTable as mhp")
                            ->whereRaw('mhp.permission_id = p.id')
                            ->where('mhp.model_type', '=', get_class($user))
                            ->where('mhp.model_id', '=', $user->getKey());
                    })->orWhereExists(function (QueryBuilder $query) use (
                        $user, $roleHasPermissionsTable, $modelHasRolesTable
                    ) {
                        $query->select(DB::raw(1))
                            ->from("{$roleHasPermissionsTable} as rhp")
                            ->whereRaw('rhp.permission_id = p.id')
                            ->join("{$modelHasRolesTable} as mhr", function (JoinClause $join) use ($user) {
                                $join->on('rhp.role_id', '=', 'mhr.role_id')
                                    ->where('mhr.model_type', '=', get_class($user))
                                    ->where('mhr.model_id', '=', $user->getKey());
                            });
                    });
                });
        });
    }
}

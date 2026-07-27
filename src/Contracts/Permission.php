<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Contracts;

use BBSLab\NovaPermission\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission as Contract;

/**
 * @mixin Model
 */
interface Permission extends Contract
{
    /**
     * @return MorphTo<Model, Model>
     */
    public function authorizable(): MorphTo;

    /**
     * @param  Collection<int, Role>|null  $roles
     * @return array<string, mixed>
     */
    public function serializeForPermissionBuilder(?Collection $roles = null): array;
}

<?php

namespace BBSLab\NovaPermission\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission as Contract;

/**
 * Interface Permission.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
interface Permission extends Contract
{
    /**
     * A permission can concern a specific model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function authorizable(): MorphTo;

    /**
     * Get permission builder representation.
     *
     * @param  \Illuminate\Support\Collection  $roles
     * @return array
     */
    public function serializeForPermissionBuilder(Collection $roles = null): array;
}

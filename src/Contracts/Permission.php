<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission as Contract;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
interface Permission extends Contract
{
    public function authorizable(): MorphTo;

    public function serializeForPermissionBuilder(?Collection $roles = null): array;
}

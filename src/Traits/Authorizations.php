<?php

namespace BBSLab\NovaPermission\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Permission\PermissionRegistrar;

trait Authorizations
{
    public function authorizations(): MorphMany
    {
        /** @var \Spatie\Permission\PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);

        return $this->morphMany(get_class($registrar->getPermissionClass()), 'authorizable');
    }
}

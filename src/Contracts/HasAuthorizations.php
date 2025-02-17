<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasAuthorizations
{
    public function authorizations(): MorphMany;
}

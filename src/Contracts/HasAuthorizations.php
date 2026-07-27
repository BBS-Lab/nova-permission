<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasAuthorizations
{
    /**
     * @return MorphMany<Model, Model>
     */
    public function authorizations(): MorphMany;
}

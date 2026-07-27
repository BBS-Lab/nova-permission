<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use BBSLab\NovaPermission\Contracts\HasAuthorizations;
use BBSLab\NovaPermission\Traits\Authorizations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workbench\Database\Factories\ServiceFactory;

/**
 * A model whose individual instances can carry their own permissions
 * ("granular" / authorizable permissions) — e.g. "manage this specific service".
 */
class Service extends Model implements HasAuthorizations
{
    use Authorizations;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = ['name'];
}

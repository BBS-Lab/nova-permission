<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use BBSLab\NovaPermission\Contracts\HasAuthorizations;
use BBSLab\NovaPermission\Traits\Authorizations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;

class Post extends Model implements HasAuthorizations
{
    use Authorizations;

    protected $fillable = ['title', 'content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use BBSLab\NovaPermission\Contracts\HasAuthorizations;
use BBSLab\NovaPermission\Traits\Authorizations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;
use Workbench\Database\Factories\PostFactory;

class Post extends Model implements HasAuthorizations
{
    use Authorizations;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = ['title', 'content'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

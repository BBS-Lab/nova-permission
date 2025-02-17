<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

use BBSLab\NovaPermission\Policies\Policy;
use Workbench\App\Models\Post;

class PostPolicy extends Policy
{
    protected function model(): string
    {
        return Post::class;
    }
}

<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Nova\Http\Requests\NovaRequest;

class ToolController extends Controller
{
    public function __invoke(NovaRequest $request): Response
    {
        return Inertia::render('PermissionBuilder');
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The workbench is a Nova app — send the root (and the post-impersonation
// redirect, which targets "/") straight to Nova.
Route::get('/', fn () => redirect('/nova'));

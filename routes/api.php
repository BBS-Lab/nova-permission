<?php

use Illuminate\Support\Facades\Route;
use BBSLab\NovaPermission\Http\Controllers\PermissionController;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. They are protected
| by your tool's "Authorize" middleware by default. Now, go build!
|
*/

Route::get('/groups', [PermissionController::class, 'groups']);

Route::group([
    'prefix' => 'permissions'
], function () {
    Route::post('/group', [PermissionController::class, 'permissionsByGroup']);
    Route::post('/authorizable', [PermissionController::class, 'permissionsByAuthorizable']);
    Route::post('/{role}/attach', [PermissionController::class, 'attachPermission']);
    Route::get('/generate', [PermissionController::class, 'generatePermission']);
});

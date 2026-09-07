<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\Role\PermissionCatalogController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\User\ActivateUserController;
use App\Http\Controllers\Api\V1\User\AssignUserRolesController;
use App\Http\Controllers\Api\V1\User\DisableUserController;
use App\Http\Controllers\Api\V1\User\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class);
Route::get('/ping', PingController::class);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', MeController::class);
    Route::post('/auth/logout', LogoutController::class);

    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::patch('/organization', [OrganizationController::class, 'update']);

    Route::scopeBindings()->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/disable', DisableUserController::class);
        Route::post('/users/{user}/activate', ActivateUserController::class);
        Route::put('/users/{user}/roles', AssignUserRolesController::class);

        Route::get('/roles/permissions-catalog', PermissionCatalogController::class);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::patch('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    });
});

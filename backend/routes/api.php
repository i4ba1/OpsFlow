<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Customer\CustomerContactController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\Project\ProjectController;
use App\Http\Controllers\Api\V1\Role\PermissionCatalogController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\ServiceRequest\ServiceRequestController;
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

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
        Route::patch('/customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);

        Route::get('/customers/{customer}/contacts', [CustomerContactController::class, 'index']);
        Route::post('/customers/{customer}/contacts', [CustomerContactController::class, 'store']);

        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::patch('/projects/{project}', [ProjectController::class, 'update']);
        Route::post('/projects/{project}/archive', [ProjectController::class, 'archive']);

        Route::get('/service-requests', [ServiceRequestController::class, 'index']);
        Route::post('/service-requests', [ServiceRequestController::class, 'store']);
        Route::get('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'show']);
        Route::patch('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'update']);
        Route::post('/service-requests/{serviceRequest}/assign', [ServiceRequestController::class, 'assign']);
        Route::post('/service-requests/{serviceRequest}/priority', [ServiceRequestController::class, 'priority']);
        Route::post('/service-requests/{serviceRequest}/transition', [ServiceRequestController::class, 'transition']);
        Route::get('/service-requests/{serviceRequest}/history', [ServiceRequestController::class, 'history']);
    });
});

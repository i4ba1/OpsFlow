<?php

use App\Http\Controllers\Api\V1\Approval\ApprovalController;
use App\Http\Controllers\Api\V1\Attachment\AttachmentController;
use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Comment\CommentController;
use App\Http\Controllers\Api\V1\Customer\CustomerContactController;
use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Invoice\InvoiceController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\Project\ProjectController;
use App\Http\Controllers\Api\V1\Role\PermissionCatalogController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\ServiceRequest\ServiceRequestController;
use App\Http\Controllers\Api\V1\SLA\BusinessCalendarController;
use App\Http\Controllers\Api\V1\SLA\SlaPolicyController;
use App\Http\Controllers\Api\V1\User\ActivateUserController;
use App\Http\Controllers\Api\V1\User\AssignUserRolesController;
use App\Http\Controllers\Api\V1\User\DirectoryController;
use App\Http\Controllers\Api\V1\User\DisableUserController;
use App\Http\Controllers\Api\V1\User\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class)->middleware('throttle:login');
Route::get('/ping', PingController::class);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', MeController::class);
    Route::post('/auth/logout', LogoutController::class);

    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::patch('/organization', [OrganizationController::class, 'update']);

    Route::get('/directory/users', DirectoryController::class);

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

        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
        Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);
        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void']);

        Route::get('/service-requests', [ServiceRequestController::class, 'index']);
        Route::post('/service-requests', [ServiceRequestController::class, 'store']);
        Route::get('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'show']);
        Route::patch('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'update']);
        Route::post('/service-requests/{serviceRequest}/assign', [ServiceRequestController::class, 'assign']);
        Route::post('/service-requests/{serviceRequest}/priority', [ServiceRequestController::class, 'priority']);
        Route::post('/service-requests/{serviceRequest}/transition', [ServiceRequestController::class, 'transition']);
        Route::get('/service-requests/{serviceRequest}/history', [ServiceRequestController::class, 'history']);
        Route::get('/service-requests/{serviceRequest}/comments', [CommentController::class, 'index']);
        Route::post('/service-requests/{serviceRequest}/comments', [CommentController::class, 'store']);
        Route::get('/service-requests/{serviceRequest}/attachments', [AttachmentController::class, 'index']);
        Route::post('/service-requests/{serviceRequest}/attachments', [AttachmentController::class, 'store']);
        Route::post('/service-requests/{serviceRequest}/approvals', [ApprovalController::class, 'store']);

        Route::get('/approvals', [ApprovalController::class, 'index']);
        Route::get('/approvals/{approval}', [ApprovalController::class, 'show']);
        Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve']);
        Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject']);
        Route::post('/approvals/{approval}/cancel', [ApprovalController::class, 'cancel']);

        Route::get('/sla-policies', [SlaPolicyController::class, 'index']);
        Route::post('/sla-policies', [SlaPolicyController::class, 'store']);
        Route::get('/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'show']);
        Route::patch('/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'update']);

        Route::get('/business-calendars', [BusinessCalendarController::class, 'index']);
        Route::post('/business-calendars', [BusinessCalendarController::class, 'store']);
        Route::get('/business-calendars/{businessCalendar}', [BusinessCalendarController::class, 'show']);
        Route::patch('/business-calendars/{businessCalendar}', [BusinessCalendarController::class, 'update']);

        Route::patch('/comments/{comment}', [CommentController::class, 'update']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

        Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download']);
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);

        Route::prefix('dashboard')->group(function (): void {
            Route::get('/overview', [DashboardController::class, 'overview']);
            Route::get('/request-volume', [DashboardController::class, 'requestVolume']);
            Route::get('/sla', [DashboardController::class, 'sla']);
            Route::get('/team-performance', [DashboardController::class, 'teamPerformance']);
        });

        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show']);
    });
});

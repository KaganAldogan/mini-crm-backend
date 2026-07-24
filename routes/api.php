<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerInteractionController;
use App\Http\Controllers\Api\CustomerTypeController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ListingTypeController;
use App\Http\Controllers\Api\PropertyTypeController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\CustomerApplicationController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\LeaseDocumentController;
use App\Http\Controllers\Api\LeaseMessageController;
use App\Http\Controllers\Api\LeasePaymentController;
use App\Http\Controllers\Api\TenantPortalController;
use App\Http\Controllers\Api\LandlordPortalController;
use App\Http\Controllers\Api\TenantMaintenanceController;
use App\Http\Controllers\Api\TechnicianMaintenanceController;
use App\Http\Controllers\Api\StaffMaintenanceController;
use App\Http\Controllers\Api\MaintenanceCategoryController;
use App\Http\Controllers\Api\PropertyInterestEventController;
use App\Http\Controllers\Api\PortalPreferencesController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/2fa', [AuthController::class, 'verifyTwoFactor']);
Route::post('/customer-applications', [CustomerApplicationController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/two-factor', [AuthController::class, 'twoFactorStatus']);
    Route::post('/two-factor/enable', [AuthController::class, 'enableTwoFactor']);
    Route::post('/two-factor/confirm', [AuthController::class, 'confirmTwoFactor']);
    Route::post('/two-factor/disable', [AuthController::class, 'disableTwoFactor']);

    Route::middleware('tenant')->prefix('portal/tenant')->group(function () {
        Route::get('/me', [TenantPortalController::class, 'me']);
        Route::get('/preferences', [PortalPreferencesController::class, 'show']);
        Route::put('/preferences', [PortalPreferencesController::class, 'update']);
        Route::get('/leases', [TenantPortalController::class, 'leases']);
        Route::get('/leases/{lease}', [TenantPortalController::class, 'showLease']);
        Route::get('/leases/{lease}/documents', [TenantPortalController::class, 'documentsForLease']);
        Route::get('/leases/{lease}/documents/{document}/download', [TenantPortalController::class, 'downloadDocumentForLease']);
        Route::get('/lease', [TenantPortalController::class, 'lease']);
        Route::get('/lease/documents', [TenantPortalController::class, 'documents']);
        Route::get('/lease/documents/{document}/download', [TenantPortalController::class, 'downloadDocument']);
        Route::get('/lease/messages', [TenantPortalController::class, 'messages']);
        Route::post('/lease/messages', [TenantPortalController::class, 'storeMessage']);
        Route::post('/lease/messages/read', [TenantPortalController::class, 'markMessagesRead']);
        Route::get('/lease/consultant-messages', [TenantPortalController::class, 'consultantMessages']);
        Route::post('/lease/consultant-messages', [TenantPortalController::class, 'storeConsultantMessage']);
        Route::post('/lease/consultant-messages/read', [TenantPortalController::class, 'markConsultantMessagesRead']);
        Route::get('/maintenance/meta', [TenantMaintenanceController::class, 'meta']);
        Route::get('/maintenance', [TenantMaintenanceController::class, 'index']);
        Route::post('/maintenance', [TenantMaintenanceController::class, 'store']);
        Route::get('/maintenance/{maintenanceRequest}', [TenantMaintenanceController::class, 'show']);
        Route::post('/maintenance/{maintenanceRequest}/cancel', [TenantMaintenanceController::class, 'cancel']);
        Route::post('/maintenance/{maintenanceRequest}/confirm-completion', [TenantMaintenanceController::class, 'confirmCompletion']);
        Route::post('/maintenance/{maintenanceRequest}/dispute-completion', [TenantMaintenanceController::class, 'disputeCompletion']);
    });

    Route::middleware('landlord')->prefix('portal/landlord')->group(function () {
        Route::get('/me', [LandlordPortalController::class, 'me']);
        Route::get('/preferences', [PortalPreferencesController::class, 'show']);
        Route::put('/preferences', [PortalPreferencesController::class, 'update']);
        Route::get('/summary', [LandlordPortalController::class, 'summary']);
        Route::get('/interest-events', [LandlordPortalController::class, 'interestEvents']);
        Route::get('/properties', [LandlordPortalController::class, 'properties']);
        Route::get('/properties/{property}', [LandlordPortalController::class, 'showProperty']);
        Route::get('/leases', [LandlordPortalController::class, 'leases']);
        Route::get('/leases/{lease}', [LandlordPortalController::class, 'showLease']);
        Route::get('/leases/{lease}/documents', [LandlordPortalController::class, 'documents']);
        Route::get('/leases/{lease}/documents/{document}/download', [LandlordPortalController::class, 'downloadDocument']);
        Route::get('/leases/{lease}/messages', [LandlordPortalController::class, 'messages']);
        Route::post('/leases/{lease}/messages', [LandlordPortalController::class, 'storeMessage']);
        Route::post('/leases/{lease}/messages/read', [LandlordPortalController::class, 'markMessagesRead']);
        Route::get('/leases/{lease}/consultant-messages', [LandlordPortalController::class, 'consultantMessages']);
        Route::post('/leases/{lease}/consultant-messages', [LandlordPortalController::class, 'storeConsultantMessage']);
        Route::post('/leases/{lease}/consultant-messages/read', [LandlordPortalController::class, 'markConsultantMessagesRead']);
    });

    Route::middleware('technician')->prefix('portal/technician')->group(function () {
        Route::get('/maintenance/summary', [TechnicianMaintenanceController::class, 'summary']);
        Route::get('/maintenance/technicians', [TechnicianMaintenanceController::class, 'technicians']);
        Route::get('/maintenance', [TechnicianMaintenanceController::class, 'index']);
        Route::get('/maintenance/{maintenanceRequest}', [TechnicianMaintenanceController::class, 'show']);
        Route::post('/maintenance/{maintenanceRequest}/decide', [TechnicianMaintenanceController::class, 'decide']);
        Route::post('/maintenance/{maintenanceRequest}/decline', [TechnicianMaintenanceController::class, 'decline']);
        Route::post('/maintenance/{maintenanceRequest}/schedule', [TechnicianMaintenanceController::class, 'schedule']);
        Route::post('/maintenance/{maintenanceRequest}/complete', [TechnicianMaintenanceController::class, 'complete']);
    });

    Route::middleware('staff')->group(function () {
        Route::get('/dashboard-stats', [DashboardController::class, 'stats']);
        Route::get('/customers-stats', [CustomerController::class, 'stats']);
        Route::get('/maintenance', [StaffMaintenanceController::class, 'index']);
        Route::get('/maintenance/technicians', [StaffMaintenanceController::class, 'technicians']);
        Route::get('/maintenance/{maintenanceRequest}', [StaffMaintenanceController::class, 'show']);
        Route::post('/maintenance/{maintenanceRequest}/decide', [StaffMaintenanceController::class, 'decide']);
        Route::post('/maintenance/{maintenanceRequest}/force-complete', [StaffMaintenanceController::class, 'forceComplete']);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('properties', PropertyController::class);
        Route::post('/properties/{property}/cover', [PropertyController::class, 'uploadCover']);
        Route::delete('/properties/{property}/cover', [PropertyController::class, 'deleteCover']);
        Route::post('/properties/{property}/images', [PropertyController::class, 'uploadImages']);
        Route::delete('/properties/{property}/images/{image}', [PropertyController::class, 'deleteImage']);
        Route::get('/interest-events', [PropertyInterestEventController::class, 'all']);
        Route::get('/properties/{property}/interest-events', [PropertyInterestEventController::class, 'index']);
        Route::post('/properties/{property}/interest-events', [PropertyInterestEventController::class, 'store']);
        Route::delete('/properties/{property}/interest-events/{event}', [PropertyInterestEventController::class, 'destroy']);
        Route::apiResource('property-types', PropertyTypeController::class)->except(['show']);
        Route::apiResource('customer-types', CustomerTypeController::class)->except(['show']);
        Route::apiResource('maintenance-categories', MaintenanceCategoryController::class)->except(['show']);
        Route::apiResource('listing-types', ListingTypeController::class)->except(['show']);

        Route::get('/leases/tenant-users', [LeaseController::class, 'tenantUsers']);
        Route::apiResource('leases', LeaseController::class);
        Route::get('/payments', [LeasePaymentController::class, 'indexAll']);
        Route::get('/leases/{lease}/payments', [LeasePaymentController::class, 'index']);
        Route::post('/leases/{lease}/payments', [LeasePaymentController::class, 'store']);
        Route::put('/leases/{lease}/payments/{payment}', [LeasePaymentController::class, 'update']);
        Route::delete('/leases/{lease}/payments/{payment}', [LeasePaymentController::class, 'destroy']);

        Route::get('/leases/{lease}/documents', [LeaseDocumentController::class, 'index']);
        Route::post('/leases/{lease}/documents', [LeaseDocumentController::class, 'store']);
        Route::get('/leases/{lease}/documents/{document}/download', [LeaseDocumentController::class, 'download']);
        Route::delete('/leases/{lease}/documents/{document}', [LeaseDocumentController::class, 'destroy']);

        Route::get('/message-conversations', [LeaseMessageController::class, 'conversations']);
        Route::get('/leases/{lease}/messages', [LeaseMessageController::class, 'index']);
        Route::post('/leases/{lease}/messages', [LeaseMessageController::class, 'store']);
        Route::post('/leases/{lease}/messages/read', [LeaseMessageController::class, 'markRead']);

        Route::get('/customers/{customer}/interactions', [CustomerInteractionController::class, 'index']);
        Route::post('/customers/{customer}/interactions', [CustomerInteractionController::class, 'store']);
        Route::put('/customers/{customer}/interactions/{interaction}', [CustomerInteractionController::class, 'update']);
        Route::delete('/customers/{customer}/interactions/{interaction}', [CustomerInteractionController::class, 'destroy']);

        Route::middleware('admin')->group(function () {
            Route::apiResource('users', UserController::class)->except(['show']);
            Route::get('/permissions', [PermissionController::class, 'index']);
            Route::apiResource('roles', RoleController::class);

            Route::get('/customer-applications', [CustomerApplicationController::class, 'index']);
            Route::get('/customer-applications/{customer_application}', [CustomerApplicationController::class, 'show']);
            Route::post('/customer-applications/{customer_application}/approve', [CustomerApplicationController::class, 'approve']);
            Route::post('/customer-applications/{customer_application}/reject', [CustomerApplicationController::class, 'reject']);
        });
    });
});

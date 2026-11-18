<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\EstimateController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ServiceCatalogController;
use App\Http\Controllers\Api\V1\ServiceRequestController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public Auth
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:8,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:8,1');

        // Authenticated Session & Account
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('switch-company', [AuthController::class, 'switchCompany']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::get('invitations', [AuthController::class, 'listInvitations']);
            Route::post('invitations', [AuthController::class, 'inviteTeamMember']);
            Route::delete('invitations/{invitation}', [AuthController::class, 'revokeInvitation']);
        });
    });

    // Multi-Tenant Protected Business API
    Route::middleware(['auth:sanctum', 'company.context'])->group(function () {

        // Customers
        Route::apiResource('customers', CustomerController::class);

        // Service Catalog
        Route::get('service-categories', [ServiceCatalogController::class, 'categories']);
        Route::post('service-categories', [ServiceCatalogController::class, 'storeCategory']);
        Route::apiResource('services', ServiceCatalogController::class);

        // Service Requests
        Route::apiResource('service-requests', ServiceRequestController::class)->parameters(['service-requests' => 'serviceRequest']);

        // Appointments
        Route::apiResource('appointments', AppointmentController::class);

        // Estimates & Workflow Actions
        Route::post('estimates/{estimate}/send', [EstimateController::class, 'send']);
        Route::post('estimates/{estimate}/approve', [EstimateController::class, 'approve']);
        Route::post('estimates/{estimate}/reject', [EstimateController::class, 'reject']);
        Route::apiResource('estimates', EstimateController::class);

        // Work Orders & Field Operations
        Route::post('work-orders/{workOrder}/start', [WorkOrderController::class, 'start']);
        Route::post('work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete']);
        Route::post('work-orders/{workOrder}/checklists', [WorkOrderController::class, 'addChecklist']);
        Route::put('work-orders/{workOrder}/checklists/{checklistId}', [WorkOrderController::class, 'toggleChecklist']);
        Route::post('work-orders/{workOrder}/photos', [WorkOrderController::class, 'uploadPhoto']);
        Route::apiResource('work-orders', WorkOrderController::class)->parameters(['work-orders' => 'workOrder']);

        // Invoices & Billing
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void']);
        Route::apiResource('invoices', InvoiceController::class);

        // Payments
        Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);
    });
});

<?php

use App\Http\Controllers\Api\V1\AssetAssignmentController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AssetRequestController;
use App\Http\Controllers\Api\V1\AssetTransferController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\DisposalController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\LifecycleReviewController;
use App\Http\Controllers\Api\V1\MaintenanceTicketController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\PurchaseOrderReceivingController;
use App\Http\Controllers\Api\V1\PurchaseRequestController;
use App\Http\Controllers\Api\V1\PurchaseRequestDecisionController;
use App\Http\Controllers\Api\V1\PurchaseRequestResubmissionController;
use App\Http\Controllers\Api\V1\RepairController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');
    Route::post('/auth/token', [AuthController::class, 'storeToken'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'show']);
        Route::delete('/auth/token', [AuthController::class, 'destroy']);
        Route::get('/dashboard', [DashboardController::class, 'show']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markRead']);
        Route::get('/directory/users', [DirectoryController::class, 'users']);
        Route::get('/directory/locations', [DirectoryController::class, 'locations']);
        Route::get('/directory/suppliers', [DirectoryController::class, 'suppliers']);

        Route::get('/asset-categories', [PurchaseRequestController::class, 'categories']);
        Route::apiResource('purchase-requests', PurchaseRequestController::class)
            ->only(['index', 'store', 'show']);
        Route::post('/purchase-requests/{purchaseRequest}/decision', [PurchaseRequestDecisionController::class, 'store']);
        Route::post('/purchase-requests/{purchaseRequest}/resubmissions', [PurchaseRequestResubmissionController::class, 'store']);

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::post('/purchase-orders/{purchaseOrder}/receiving', [PurchaseOrderReceivingController::class, 'store']);

        Route::get('/assets', [AssetController::class, 'index']);
        Route::get('/my-assets', [AssetController::class, 'myAssets']);
        Route::get('/assets/{asset}', [AssetController::class, 'show']);
        Route::get('/reports/assets.csv', [ReportController::class, 'assetsCsv']);
        Route::get('/reports/assets.pdf', [ReportController::class, 'assetsPdf']);

        Route::get('/asset-requests', [AssetRequestController::class, 'index']);
        Route::post('/asset-requests', [AssetRequestController::class, 'store']);
        Route::post('/asset-requests/{assetRequest}/assignments', [AssetAssignmentController::class, 'store']);
        Route::post('/assignments/{assetAssignment}/acknowledgement', [AssetAssignmentController::class, 'acknowledge']);
        Route::post('/assignments/{assetAssignment}/check-in', [AssetAssignmentController::class, 'checkIn']);
        Route::get('/assignments/{assetAssignment}/accountability-form', [AssetAssignmentController::class, 'form']);
        Route::get('/assignments/{assetAssignment}/evidence', [AssetAssignmentController::class, 'evidence']);

        Route::get('/transfers', [AssetTransferController::class, 'index']);
        Route::post('/transfers', [AssetTransferController::class, 'store']);
        Route::post('/transfers/{assetTransfer}/approval', [AssetTransferController::class, 'approve']);
        Route::post('/transfers/{assetTransfer}/acceptance', [AssetTransferController::class, 'accept']);

        Route::get('/maintenance-tickets', [MaintenanceTicketController::class, 'index']);
        Route::post('/maintenance-tickets', [MaintenanceTicketController::class, 'store']);
        Route::put('/maintenance-tickets/{maintenanceTicket}/checklist', [MaintenanceTicketController::class, 'checklist']);
        Route::post('/maintenance-tickets/{maintenanceTicket}/repairs', [RepairController::class, 'store']);
        Route::post('/repairs/{repair}/cost-approval', [RepairController::class, 'approve']);
        Route::post('/repairs/{repair}/completion', [RepairController::class, 'complete']);

        Route::get('/licenses', [LicenseController::class, 'index']);
        Route::get('/license-requests', [LicenseController::class, 'requests']);
        Route::post('/license-requests', [LicenseController::class, 'storeRequest']);
        Route::post('/license-requests/{licenseRequest}/decision', [LicenseController::class, 'decideRequest']);
        Route::post('/license-requests/{licenseRequest}/record', [LicenseController::class, 'record']);
        Route::post('/licenses/{softwareLicense}/assignments', [LicenseController::class, 'assign']);
        Route::delete('/licenses/{softwareLicense}/assignments/{licenseAssignment}', [LicenseController::class, 'revoke']);
        Route::post('/licenses/compliance-checks', [LicenseController::class, 'compliance']);
        Route::get('/licenses/compliance-checks', [LicenseController::class, 'complianceHistory']);
        Route::get('/licenses/renewal-reviews', [LicenseController::class, 'renewalReviews']);
        Route::post('/licenses/renewal-reviews/{licenseRenewalReview}', [LicenseController::class, 'reviewRenewal']);

        Route::get('/lifecycle/reviews', [LifecycleReviewController::class, 'index']);
        Route::post('/lifecycle/reviews/scan', [LifecycleReviewController::class, 'scan']);
        Route::get('/disposals', [DisposalController::class, 'index']);
        Route::post('/disposals', [DisposalController::class, 'store']);
        Route::post('/disposals/{disposal}/finance-approval', [DisposalController::class, 'approveFinance']);
        Route::post('/disposals/{disposal}/management-approval', [DisposalController::class, 'approveManagement']);
        Route::post('/disposals/{disposal}/completion', [DisposalController::class, 'complete']);
        Route::get('/disposals/{disposal}/certificate', [DisposalController::class, 'certificate']);
    });
});

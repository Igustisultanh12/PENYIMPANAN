<?php

use App\Http\Controllers\Api\V1\Admin\AdminSettingController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\OfficeController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\ShareController;
use App\Http\Controllers\Api\V1\StorageController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\UploadChunkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MyStorage REST API V1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Public Authentication & Verification Routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::get('verify-email/{token}', [AuthController::class, 'verifyEmail']);
        Route::post('resend-verification', [AuthController::class, 'resendVerification']);
        Route::get('google/redirect', [AuthController::class, 'googleRedirect']);
        Route::get('google/callback', [AuthController::class, 'googleCallback']);
    });

    // Public / Signed Share & Download Endpoints
    Route::get('shares/public/{token}', [ShareController::class, 'accessPublic']);
    Route::get('shares/public/{token}/download', [ShareController::class, 'downloadPublic']);
    Route::get('files/download/signed/{file}', [FileController::class, 'streamDownload'])
        ->name('api.v1.files.download.signed');

    // Authenticated API Endpoints
    Route::middleware(['auth:sanctum', 'account.active'])->group(function () {

        // Session & Auth Profile
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Administrative Management Routes
        Route::prefix('admin')->group(function () {
            Route::get('settings', [AdminSettingController::class, 'getSettings']);
            Route::post('settings', [AdminSettingController::class, 'updateSettings']);
            Route::get('whatsapp/status', [AdminSettingController::class, 'getWhatsAppStatus']);
            Route::post('whatsapp/test', [AdminSettingController::class, 'testWhatsApp']);
            Route::post('whatsapp/reset', [AdminSettingController::class, 'resetWhatsAppSession']);
            Route::post('mail/test', [AdminSettingController::class, 'testMail']);
        });

        // Storage & Drive Endpoints (Requires Verified Email)
        Route::middleware(['email.verified'])->group(function () {

            // Folder Management
            Route::apiResource('folders', FolderController::class)->except(['create', 'edit']);
            Route::post('folders/{uuid}/move', [FolderController::class, 'move']);

            // File Management
            Route::get('files', [FileController::class, 'index']);
            Route::post('files/upload', [FileController::class, 'upload']);
            Route::get('files/{uuid}', [FileController::class, 'show']);
            Route::patch('files/{uuid}', [FileController::class, 'update']);
            Route::post('files/{uuid}/move', [FileController::class, 'move']);
            Route::get('files/{uuid}/download', [FileController::class, 'download'])
                ->name('api.v1.files.download');
            Route::get('files/{uuid}/preview', [FileController::class, 'preview']);
            Route::delete('files/{uuid}', [FileController::class, 'destroy']);
            Route::post('files/{id}/restore', [FileController::class, 'restore']);
            Route::delete('files/{id}/permanent', [FileController::class, 'permanentDelete']);

            // Resumable Chunk Upload Engine
            Route::prefix('uploads')->group(function () {
                Route::post('initiate', [UploadChunkController::class, 'initiate']);
                Route::post('{uploadId}/chunk', [UploadChunkController::class, 'chunk']);
                Route::get('{uploadId}/status', [UploadChunkController::class, 'status']);
                Route::post('{uploadId}/finalize', [UploadChunkController::class, 'finalize']);
                Route::delete('{uploadId}', [UploadChunkController::class, 'cancel']);
            });

            // Sharing & Permissions
            Route::get('shares', [ShareController::class, 'index']);
            Route::post('shares', [ShareController::class, 'store']);
            Route::delete('shares/{uuid}', [ShareController::class, 'revoke']);

            // Storage Statistics
            Route::get('storage/stats', [StorageController::class, 'stats']);
            Route::post('storage/recalculate', [StorageController::class, 'recalculate']);

            // Global Search & WhatsApp User Lookup
            Route::get('search', [SearchController::class, 'search']);
            Route::post('search/whatsapp-lookup', [SearchController::class, 'whatsappLookup']);

            // Security Center & Developer API Tokens
            Route::prefix('security')->group(function () {
                Route::get('sessions', [SecurityController::class, 'sessions']);
                Route::post('logout-other-devices', [SecurityController::class, 'logoutOtherDevices']);
                Route::post('change-password', [SecurityController::class, 'changePassword']);
                Route::post('2fa/setup', [SecurityController::class, 'setupTwoFactor']);
                Route::post('2fa/confirm', [SecurityController::class, 'confirmTwoFactor']);
                Route::post('2fa/disable', [SecurityController::class, 'disableTwoFactor']);
                Route::get('audit-logs', [SecurityController::class, 'auditLogs']);
                Route::get('api-tokens', [SecurityController::class, 'apiTokens']);
                Route::post('api-tokens', [SecurityController::class, 'createApiToken']);
                Route::delete('api-tokens/{tokenId}', [SecurityController::class, 'revokeApiToken']);
            });

            // Desktop Sync Engine
            Route::prefix('sync')->group(function () {
                Route::get('changes', [SyncController::class, 'changes']);
                Route::post('checkpoint', [SyncController::class, 'checkpoint']);
                Route::post('conflict', [SyncController::class, 'resolveConflict']);
            });

            // Connected Desktop Devices
            Route::prefix('devices')->group(function () {
                Route::get('/', [DeviceController::class, 'index']);
                Route::post('register', [DeviceController::class, 'register']);
                Route::post('{uuid}/heartbeat', [DeviceController::class, 'heartbeat']);
                Route::patch('{uuid}/settings', [DeviceController::class, 'updateSettings']);
                Route::delete('{uuid}', [DeviceController::class, 'revoke']);
            });

            // Online Office Suite
            Route::prefix('office')->group(function () {
                Route::get('templates', [OfficeController::class, 'templates']);
                Route::post('create', [OfficeController::class, 'create']);
                Route::get('session/{fileUuid}', [OfficeController::class, 'session']);
                Route::post('draft/{sessionToken}', [OfficeController::class, 'saveDraft']);
                Route::post('commit/{sessionToken}', [OfficeController::class, 'commit']);
                Route::post('lock/{fileUuid}', [OfficeController::class, 'lock']);
                Route::delete('lock/{fileUuid}', [OfficeController::class, 'unlock']);
            });
        });
    });
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\MaintenanceTransitionController;
use App\Http\Controllers\MaintenanceJobController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\MaintenanceRatingApiController;
use App\Http\Controllers\Api\MaintenanceRequestApiController;

// Public health + auth endpoints
Route::get('/health', [HealthController::class, 'index'])->name('health');
// Optional: could add throttle here later if abused

// Public auth endpoints
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    // Password reset (API)
    Route::post('/password/email', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::post('/password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('password.reset');
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/user', fn (Request $r) => $r->user());

    // Assets API
    Route::name('api.')->group(function () {
        Route::apiResource('assets', AssetController::class);
    });

    // Repair requests (ใบงานซ่อม) - REST-ish API
    Route::prefix('repair-requests')->name('repair-requests.')->group(function () {
        Route::get('/',                   [MaintenanceRequestController::class, 'index'])->name('index');
        Route::post('/',                  [MaintenanceRequestController::class, 'store'])->name('store');
        Route::get('/{req}',              [MaintenanceRequestController::class, 'show'])->name('show');
        Route::put('/{req}',              [MaintenanceRequestController::class, 'update'])->name('update');
        Route::delete('/{req}',           [MaintenanceRequestController::class, 'destroy'])->name('destroy');
        Route::post('/{req}/transition',  [MaintenanceTransitionController::class, 'transition'])->name('transition');
        Route::get('/{req}/logs',         [MaintenanceLogController::class, 'index'])->name('logs');

        // GET /api/repair-requests/pending/evaluations
        Route::get(
            '/pending/evaluations',
            [MaintenanceRatingApiController::class, 'pendingEvaluations']
        )->name('pending-evaluations');

        // POST /api/repair-requests/{maintenanceRequest}/rating
        Route::post(
            '/{maintenanceRequest}/rating',
            [MaintenanceRatingApiController::class, 'store']
        )->name('rating.store');
    });

    // Attachments
    Route::post('/attachments',                [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Threads
    Route::get('/threads',          [ChatController::class, 'index'])->name('threads.index');
    Route::post('/threads',         [ChatController::class, 'store'])->name('threads.store');
    Route::get('/threads/{thread}', [ChatController::class, 'show'])->name('threads.show');

    // Thread messages
    Route::get('/threads/{thread}/messages',  [ChatController::class, 'messages'])->name('messages.index');
    Route::post('/threads/{thread}/messages', [ChatController::class, 'storeMessage'])->name('messages.store');

    // Thread lock / unlock (owner หรือ admin เท่านั้น – เช็คใน Controller)
    Route::post('/threads/{thread}/lock',   [ChatController::class, 'lock'])->name('threads.lock');
    Route::post('/threads/{thread}/unlock', [ChatController::class, 'unlock'])->name('threads.unlock');

    Route::get('/chat/my-updates', [ChatController::class, 'myUpdates'])->name('api.chat.my_updates');

    // Auth (protected)
    Route::get('/auth/tokens',         [AuthController::class, 'tokens'])->name('auth.tokens');
    Route::delete('/auth/tokens/{id}', [AuthController::class, 'revokeToken'])->name('auth.tokens.revoke');
    Route::get('/auth/me',             [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout',        [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/logout-all',    [AuthController::class, 'logoutAll'])->name('auth.logout-all');

    // Meta
    Route::prefix('meta')->name('meta.')->group(function () {
        Route::get('/departments', [MetaController::class, 'departments'])->name('departments');
        Route::get('/categories',  [MetaController::class, 'categories'])->name('categories');
        Route::get('/users',       [MetaController::class, 'users'])->name('users'); // ?role=it_support|network|developer|computer_officer|supervisor|admin
    });

    // Search
    Route::prefix('search')->name('search.')->group(function () {
        Route::get('/assets', [SearchController::class, 'assets'])->name('assets'); // ?q=&limit=10&department_id=
        Route::get('/maintenance-requests', [SearchController::class, 'requests'])->name('requests');
    });

    // Stats
    Route::prefix('stats')->name('stats.')->group(function () {
        Route::get('/summary',                     [StatsController::class, 'summary'])->name('summary');
        Route::get('/maintenance/status-counts',   [StatsController::class, 'maintenanceStatusCounts'])->name('maintenance.status-counts');
        Route::get('/maintenance/technicians',     [StatsController::class, 'technicianSummary'])->name('maintenance.technicians');
        Route::get('/assets/by-department',        [StatsController::class, 'assetsByDepartment'])->name('assets.by-department');
    });

    Route::get('/repair-requests/my-jobs', [MaintenanceJobController::class, 'myJobs'])->name('repair-requests.my-jobs');
});

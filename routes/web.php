<?php

use Illuminate\Support\Facades\Route;

// Auth / Profile
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PasswordController;

// App Modules (Web)
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\MaintenanceTransitionController;
use App\Http\Controllers\MaintenanceJobController;
use App\Http\Controllers\MaintenanceAttachmentController;
use App\Http\Controllers\MaintenancePrintController;
use App\Http\Controllers\Repair\DashboardController as RepairDashboardController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\MaintenanceOperationLogController;
use App\Http\Controllers\MaintenanceAssignmentController;
use App\Http\Controllers\MaintenanceRatingController;
use App\Http\Controllers\MaintenanceRequestTypeController;
use App\Http\Controllers\NotificationSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ManualController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

// login
Route::redirect('/', '/login');
Route::get('/debug/login', function() {
    Auth::loginUsingId(410);
    return redirect()->route('repair.my-jobs');
});

// Guest-only
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

// Auth-only
Route::middleware(['auth'])->group(function () {

    // Debug
    Route::get('/debug/whoami', function (Request $request) {
        $u = $request->user();

        return response()->json([
            'id'    => $u?->id,
            'email' => $u?->email,
            'role'  => $u?->role,
            'can_manage_users' => $u ? Gate::forUser($u)->allows('manage-users') : false,
            'guard' => Auth::getDefaultDriver(),
        ]);
    });

    // Dashboard
    Route::get('/repair/dashboard', [RepairDashboardController::class, 'index'])->name('repair.dashboard');
    Route::get('/dashboard', fn() => redirect()->route('repair.dashboard'))->name('dashboard');

    // Maintenance
    Route::prefix('maintenance')->name('maintenance.')->group(function () {
        Route::prefix('requests')->name('requests.')->group(function () {

            // CRUD
            Route::get('/', [MaintenanceRequestController::class, 'indexPage'])->name('index');
            Route::get('/create', [MaintenanceRequestController::class, 'createPage'])->name('create');
            Route::post('/', [MaintenanceRequestController::class, 'store'])->name('store');

            Route::get('/{req}', [MaintenanceRequestController::class, 'showPage'])->name('show');
            Route::get('/{req}/edit', [MaintenanceRequestController::class, 'edit'])->name('edit');
            Route::put('/{req}', [MaintenanceRequestController::class, 'update'])->name('update');

            // Work Order
            Route::get('/{req}/work-order', [MaintenancePrintController::class, 'printWorkOrder'])
                ->name('work-order');

            // Operation Log
            Route::post('/{maintenanceRequest}/operation-log', [MaintenanceOperationLogController::class, 'upsert'])
                ->name('operation-log');

            // Attachments
            Route::post('/{req}/attachments', [MaintenanceAttachmentController::class, 'uploadAttachmentFromBlade'])
                ->name('attachments');

            Route::delete('/{req}/attachments/{attachment}', [MaintenanceAttachmentController::class, 'destroyAttachment'])
                ->name('attachments.destroy');

            // Assignments
            Route::post('/{req}/assignments', [MaintenanceAssignmentController::class, 'store'])
                ->name('assignments.store');

            Route::delete('/{req}/assignments/{assignment}', [MaintenanceAssignmentController::class, 'destroy'])
                ->name('assignments.destroy');

            // Rating
            Route::prefix('rating')->name('rating.')->group(function () {
                Route::get('/{maintenanceRequest}/create', [MaintenanceRatingController::class, 'create'])->name('create');
                Route::post('/{maintenanceRequest}/store', [MaintenanceRatingController::class, 'store'])->name('store');
                Route::get('/evaluate', [MaintenanceRatingController::class, 'evaluateList'])->name('evaluate');

                // Team-wide performance board — same gate as the SLA dashboard.
                Route::get('/technicians', [MaintenanceRatingController::class, 'technicianDashboard'])
                    ->name('technicians')
                    ->middleware('can:maintenance-type-manage');
            });

            // Status transitions
            Route::post('/{req}/acknowledge', [MaintenanceTransitionController::class, 'acknowledgeCase'])->name('acknowledge');
            Route::post('/{req}/reject', [MaintenanceTransitionController::class, 'rejectCase'])->name('reject');
            Route::post('/{req}/accept', [MaintenanceTransitionController::class, 'acceptCase'])->name('accept');

            Route::post('/{req}/start', [MaintenanceTransitionController::class, 'startCase'])->name('start');
            Route::post('/{req}/hold', [MaintenanceTransitionController::class, 'holdCase'])->name('hold');
            Route::post('/{req}/resume', [MaintenanceTransitionController::class, 'resumeCase'])->name('resume');
            Route::post('/{req}/resolve', [MaintenanceTransitionController::class, 'resolveCase'])->name('resolve');
            Route::post('/{req}/close', [MaintenanceTransitionController::class, 'closeCase'])->name('close');

            Route::post('/{req}/cancel', [MaintenanceTransitionController::class, 'cancelCase'])->name('cancel');

            // Update report type on request
            Route::post('/{req}/type', [MaintenanceRequestController::class, 'updateType'])
                ->name('type.update');
        });
    });

    // Notifications
    Route::prefix('settings/notifications')->name('settings.notifications.')->group(function () {
        Route::get('/', [NotificationSettingController::class, 'index'])->name('index');
        Route::patch('/update-sound', [NotificationSettingController::class, 'updateSound'])->name('update_sound');
        Route::post('/upload-sound', [NotificationSettingController::class, 'uploadSound'])->name('upload_sound');
        Route::delete('/destroy-sound', [NotificationSettingController::class, 'destroySound'])->name('destroy_sound');
    });

    // Repair views
    Route::get('/repair/my-jobs', [MaintenanceJobController::class, 'myJobsPage'])->name('repairs.my_jobs');
    Route::get('/repair/queue', [MaintenanceJobController::class, 'queuePage'])->name('repairs.queue');

    // Attachments (serve private files after auth)
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/threads', [ChatController::class, 'storeThread'])->name('chat.store');
    Route::get('/chat/threads/{thread}', [ChatController::class, 'show'])->name('chat.show');
    Route::get('/chat/threads/{thread}/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::post('/chat/threads/{thread}/messages', [ChatController::class, 'storeMessage'])->name('chat.messages.store');
    Route::get('/chat/my-updates', [ChatController::class, 'myUpdates'])->name('chat.my_updates');
    Route::post('/chat/threads/{thread}/lock', [ChatController::class, 'lock'])->name('chat.lock');
    Route::post('/chat/threads/{thread}/unlock', [ChatController::class, 'unlock'])->name('chat.unlock');
    Route::delete('/chat/threads/{thread}', [ChatController::class, 'destroy'])->name('chat.destroy');

    // Assets
    // NOTE: /assets/fetch-his ต้องอยู่ก่อน /assets/{asset} เพื่อป้องกัน wildcard ชน
    Route::get('/assets/fetch-his', [AssetController::class, 'fetchHisData'])->name('assets.fetch-his');
    Route::get('/assets', [AssetController::class, 'indexPage'])->name('assets.index');
    Route::get('/assets/create', [AssetController::class, 'createPage'])->name('assets.create');
    Route::post('/assets', [AssetController::class, 'storePage'])->name('assets.store');
    Route::get('/assets/{asset}', [AssetController::class, 'showPage'])->name('assets.show');
    Route::get('/assets/{asset}/edit', [AssetController::class, 'editPage'])->name('assets.edit');
    Route::put('/assets/{asset}', [AssetController::class, 'updatePage'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroyPage'])->name('assets.destroy');
    Route::get('/assets/{asset}/print', [AssetController::class, 'printPage'])->name('assets.print');

    // Admin - Users
    Route::prefix('admin')->name('admin.')->middleware('can:manage-users')->group(function () {
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])->name('index');
            Route::get('/create', [AdminUserController::class, 'create'])->name('create');
            Route::post('/', [AdminUserController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [AdminUserController::class, 'edit'])->name('edit');
            Route::put('/{user}', [AdminUserController::class, 'update'])->name('update');
            Route::delete('/{user}', [AdminUserController::class, 'destroy'])->name('destroy');
        });
    });

    // Settings - Maintenance Types
    Route::prefix('settings/maintenance-types')
        ->name('settings.maintenance-types.')
        ->middleware('can:maintenance-type-manage')
        ->group(function () {
            Route::get('/', [MaintenanceRequestTypeController::class, 'index'])->name('index');
            Route::get('/create', [MaintenanceRequestTypeController::class, 'create'])->name('create');
            Route::post('/', [MaintenanceRequestTypeController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [MaintenanceRequestTypeController::class, 'edit'])->name('edit');
            Route::put('/{id}', [MaintenanceRequestTypeController::class, 'update'])->name('update');
            Route::delete('/{id}', [MaintenanceRequestTypeController::class, 'destroy'])->name('destroy');
        });

    // Maintenance - SLA Performance Dashboard
    Route::prefix('maintenance/sla-performance')
        ->name('maintenance.sla.')
        ->middleware('can:maintenance-type-manage')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\SlaPerformanceController::class, 'index'])->name('index');
            Route::post('/report', [\App\Http\Controllers\SlaPerformanceController::class, 'report'])->name('report');
            Route::patch('/type-default/bulk', [\App\Http\Controllers\SlaPerformanceController::class, 'bulkUpdateTypeDefault'])->name('bulk-update-type-default');
            Route::patch('/type-default/{id}', [\App\Http\Controllers\SlaPerformanceController::class, 'updateTypeDefault'])->name('update-type-default');
        });

    // Help
    Route::get('/help/manual', [ManualController::class, 'index'])->name('help.manual');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
});

    Route::get('/technicians/{user}/rating-summary', [MaintenanceRatingController::class, 'summary'])
        ->name('technicians.rating.summary')
        ->middleware(['auth', 'can:maintenance-type-manage']);

// Auth scaffolding routes
require __DIR__ . '/auth.php';

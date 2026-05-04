<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminContainerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFinanceController;
use App\Http\Controllers\Admin\AdminInquiryController;
use App\Http\Controllers\Admin\AdminOwnerController;
use App\Http\Controllers\Admin\AdminPortController;
use App\Http\Controllers\Admin\AdminRentalController;
use App\Http\Controllers\Admin\AdminRequestLogController;
use App\Http\Controllers\Admin\AdminRouteController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminUserMessageController;
use App\Http\Controllers\Admin\AdminVesselController;
use App\Http\Controllers\Api\MonitorChartsController;
use App\Http\Controllers\Api\RentalTelemetryToggleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceMonitoringController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LogisticsMapDataController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\RentalsCenterController;
use App\Http\Controllers\TelegramLinkController;
use App\Http\Controllers\UserNotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Marketing/HomePage/Index');
});

Route::get('/services', function () {
    return Inertia::render('Marketing/ServicesPage/Index');
})->name('services');

Route::get('/contact', function () {
    return Inertia::render('Marketing/ContactPage/Index');
})->name('contact');

Route::post('/contact', [InquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('contact.submit');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/finance-monitoring', [FinanceMonitoringController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('finance.monitoring');

Route::get('/rentals-center', [RentalsCenterController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('rentals.center');

Route::get('/rentals/map-data', LogisticsMapDataController::class)
    ->middleware(['auth', 'verified'])
    ->name('rentals.map-data');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('verified')->group(function () {
        Route::patch('/profile/notification-channels', [ProfileController::class, 'updateNotificationChannels'])
            ->name('profile.notification-channels.update');
        Route::post('/telegram/link-code', [TelegramLinkController::class, 'createLinkCode'])->name('telegram.link-code');
        Route::delete('/telegram/links/{link}', [TelegramLinkController::class, 'destroyLink'])->name('telegram.links.destroy');

        Route::get('/notifications', [UserNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [UserNotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');

        Route::get('/rentals/request', [RentalController::class, 'create'])->name('rentals.request.create');
        Route::post('/rentals/request/preview', [RentalController::class, 'preview'])->name('rentals.request.preview');
        Route::post('/rentals/request', [RentalController::class, 'store'])->name('rentals.request.store');
        Route::patch('/rentals/{rental}/status', [RentalController::class, 'update'])->name('rentals.status.update');
        Route::get('/rentals/{rental}/monitor', [RentalController::class, 'monitor'])->name('rentals.monitor');
        /*
        | JSON for Monitor polling uses web session (same cookies as Inertia). The /api + auth:sanctum
        | stack often returns 401 in Docker / mixed hosts because stateful domains or cookie paths differ.
        */
        Route::get('/rentals/{rental}/monitor-charts-data', [MonitorChartsController::class, 'index'])
            ->name('rentals.monitor.charts-data');
        Route::post('/rentals/{rental}/telemetry-toggle', [RentalTelemetryToggleController::class, 'toggle'])
            ->name('rentals.monitor.telemetry-toggle');
        Route::get('/rentals/{rental}/container-3d', [RentalController::class, 'container3d'])->name('rentals.container3d');
    });
});

Route::prefix('admin')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('admin.profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
    Route::patch('/profile/notification-channels', [ProfileController::class, 'updateNotificationChannels'])
        ->name('admin.profile.notification-channels.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('admin.profile.destroy');
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/inquiries', [AdminInquiryController::class, 'index'])->name('admin.inquiries.index');
    Route::patch('/inquiries/{inquiry}', [AdminInquiryController::class, 'update'])->name('admin.inquiries.update');
    Route::get('/approvals', [AdminRentalController::class, 'approvals'])->name('admin.approvals');
    Route::get('/rentals', [AdminRentalController::class, 'index'])->name('admin.rentals.index');
    Route::get('/rentals/{rental}', [AdminRentalController::class, 'show'])->name('admin.rentals.show');
    Route::get('/rentals/{rental}/full', [AdminRentalController::class, 'full'])->name('admin.rentals.full');
    Route::patch('/rentals/{rental}/status', [AdminRentalController::class, 'updateStatus'])->name('admin.rentals.status');
    Route::delete('/rentals/{rental}', [AdminRentalController::class, 'destroy'])->name('admin.rentals.destroy');
    Route::get('/finance', [AdminFinanceController::class, 'index'])->name('admin.finance.index');
    Route::get('/finance/report/export', [AdminFinanceController::class, 'reportExport'])->name('admin.finance.report.export');
    Route::get('/finance/transactions/{transaction}/history', [AdminFinanceController::class, 'transactionHistory'])->name('admin.finance.transactions.history');
    Route::patch('/finance/transactions/{transaction}', [AdminFinanceController::class, 'updateTransaction'])->name('admin.finance.transactions.update');
    Route::get('/finance/rentals/{rental}/payment-history', [AdminFinanceController::class, 'rentalPaymentHistory'])->name('admin.finance.rentals.payment-history');
    Route::patch('/finance/rentals/{rental}/payment-status', [AdminFinanceController::class, 'updateRentalPaymentStatus'])->name('admin.finance.rentals.payment-status');
    Route::post('/finance/rentals/{rental}/approve-payment', [AdminFinanceController::class, 'approvePayment'])->name('admin.finance.rentals.approve-payment');
    Route::get('/containers/archive', [AdminContainerController::class, 'archive'])->name('admin.containers.archive');
    Route::post('/containers/archive/{id}/restore', [AdminContainerController::class, 'restore'])->name('admin.containers.restore');
    Route::post('/containers/archive/{id}/force', [AdminContainerController::class, 'forceDestroy'])->name('admin.containers.force-destroy');
    Route::get('/containers/{container}/full', [AdminContainerController::class, 'full'])->name('admin.containers.full');
    Route::patch('/containers/{container}/quick', [AdminContainerController::class, 'quickUpdate'])->name('admin.containers.quick');
    Route::resource('containers', AdminContainerController::class)->names('admin.containers');
    Route::get('/ports/archive', [AdminPortController::class, 'archive'])->name('admin.ports.archive');
    Route::post('/ports/archive/{id}/restore', [AdminPortController::class, 'restore'])->name('admin.ports.restore');
    Route::post('/ports/archive/{id}/force', [AdminPortController::class, 'forceDestroy'])->name('admin.ports.force-destroy');
    Route::get('/ports/{port}/full', [AdminPortController::class, 'full'])->name('admin.ports.full');
    Route::resource('ports', AdminPortController::class)->names('admin.ports');
    Route::get('/routes/archive', [AdminRouteController::class, 'archive'])->name('admin.routes.archive');
    Route::post('/routes/archive/{id}/restore', [AdminRouteController::class, 'restore'])->name('admin.routes.restore');
    Route::post('/routes/archive/{id}/force', [AdminRouteController::class, 'forceDestroy'])->name('admin.routes.force-destroy');
    Route::resource('routes', AdminRouteController::class)->names('admin.routes')->parameters(['routes' => 'route']);
    Route::get('/vessels/archive', [AdminVesselController::class, 'archive'])->name('admin.vessels.archive');
    Route::post('/vessels/archive/{id}/restore', [AdminVesselController::class, 'restore'])->name('admin.vessels.restore');
    Route::post('/vessels/archive/{id}/force', [AdminVesselController::class, 'forceDestroy'])->name('admin.vessels.force-destroy');
    Route::resource('vessels', AdminVesselController::class)->names('admin.vessels');
    Route::get('/owners/archive', [AdminOwnerController::class, 'archive'])->name('admin.owners.archive');
    Route::post('/owners/archive/{id}/restore', [AdminOwnerController::class, 'restore'])->name('admin.owners.restore');
    Route::post('/owners/archive/{id}/force', [AdminOwnerController::class, 'forceDestroy'])->name('admin.owners.force-destroy');
    Route::resource('owners', AdminOwnerController::class)->names('admin.owners');
    Route::get('/users/archive', [AdminUserController::class, 'archive'])->name('admin.users.archive');
    Route::post('/users/archive/{id}/restore', [AdminUserController::class, 'restore'])->name('admin.users.restore');
    Route::post('/users/archive/{id}/force', [AdminUserController::class, 'forceDestroy'])->name('admin.users.force-destroy');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::post('/users/{user}/messages', [AdminUserMessageController::class, 'store'])->name('admin.users.messages.store');
    Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])->name('admin.activity-logs.index');
    Route::get('/activity-logs/{activityLog}', [AdminActivityLogController::class, 'show'])->name('admin.activity-logs.show');
    Route::get('/request-logs', [AdminRequestLogController::class, 'index'])->name('admin.request-logs.index');
    Route::get('/request-logs/user/{user}/chain', [AdminRequestLogController::class, 'userChain'])->name('admin.request-logs.user-chain');
});

require __DIR__.'/auth.php';

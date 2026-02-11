<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InstallerController;

// Installer Routes
Route::middleware(['not.installed'])->group(function () {
    Route::get('install', [InstallerController::class, 'welcome'])->name('install.welcome');
    Route::get('install/permissions', [InstallerController::class, 'permissions'])->name('install.permissions');
    Route::get('install/database', [InstallerController::class, 'database'])->name('install.database');
    Route::post('install/database', [InstallerController::class, 'processDatabase'])->name('install.database.post');
    Route::get('install/migrations', [InstallerController::class, 'migrations'])->name('install.migrations');
    Route::get('install/register', [InstallerController::class, 'register'])->name('install.register');
    Route::post('install/register', [InstallerController::class, 'processRegister'])->name('install.register.post');
});

Route::get('/', function () {
    return Inertia::render('Vicoee/Home');
})->name('home');

Route::get('contact-us', function () {
    return Inertia::render('Vicoee/Contact');
})->name('contact');

Route::post('contact', [ContactController::class, 'store']);

Route::get('dashboard', function () {
    $user = auth()->user();
    if ($user && $user->hasPermission('kiosk.view') && !$user->isAdmin() && !$user->hasPermission('oee.view')) {
        return redirect()->route('operator');
    }
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('operator', [\App\Http\Controllers\OperatorController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('operator');

Route::get('/analytics', function () {
    return Inertia::render('Analytics/AdvancedAnalytics');
})->middleware(['auth', 'verified'])->name('analytics');

Route::get('/andon', function () {
    return Inertia::render('Andon');
})->middleware(['auth', 'verified'])->name('andon');

Route::get('/admin/alert-rules', function () {
    return Inertia::render('Admin/AlertRules');
})->middleware(['auth', 'verified', \App\Http\Middleware\EnsureUserIsAdmin::class])->name('admin.alert-rules');

Route::middleware(['auth', 'verified', \App\Http\Middleware\EnsureUserIsAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    // Configuration / Assets
    Route::resource('plants', App\Http\Controllers\Admin\PlantController::class);
    Route::resource('lines', App\Http\Controllers\Admin\LineController::class);
    Route::resource('machines', App\Http\Controllers\Admin\MachineController::class);
    Route::post('machines/{machine}/shifts', [App\Http\Controllers\Admin\MachineController::class, 'attachShift'])->name('machines.shifts.attach');
    Route::delete('machines/{machine}/shifts/{shift}', [App\Http\Controllers\Admin\MachineController::class, 'detachShift'])->name('machines.shifts.detach');
    Route::post('machines/{machine}/products', [App\Http\Controllers\Admin\MachineController::class, 'assignProduct'])->name('machines.products.assign');
    Route::delete('machines/{machine}/products/{product}', [App\Http\Controllers\Admin\MachineController::class, 'detachProduct'])->name('machines.products.detach');
    Route::post('machines/{machine}/reasons', [App\Http\Controllers\Admin\MachineController::class, 'assignReason'])->name('machines.reasons.assign');
    Route::delete('machines/{machine}/reasons/{reasonCode}', [App\Http\Controllers\Admin\MachineController::class, 'detachReason'])->name('machines.reasons.detach');
    Route::resource('products', App\Http\Controllers\Admin\ProductController::class);
    Route::resource('reason-codes', App\Http\Controllers\Admin\ReasonCodeController::class);
    Route::resource('material-loss-categories', App\Http\Controllers\Admin\MaterialLossCategoryController::class);
    Route::resource('downtime-types', App\Http\Controllers\Admin\DowntimeTypeController::class);
    Route::resource('loss-types', App\Http\Controllers\Admin\LossTypeController::class);
    Route::resource('shifts', App\Http\Controllers\Admin\ShiftController::class);
    Route::resource('targets', App\Http\Controllers\Admin\TargetController::class);
    Route::get('targets/active/{machineId}', [App\Http\Controllers\Admin\TargetController::class, 'getActiveTarget'])->name('targets.active');
    
    // Organization
    Route::put('organization/{organization}', [App\Http\Controllers\Admin\OrganizationController::class, 'update'])->name('organization.update');
    
    // Site Settings
    Route::get('settings', [App\Http\Controllers\Admin\SiteSettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [App\Http\Controllers\Admin\SiteSettingController::class, 'update'])->name('settings.update');
    
    // Unit Conversions
    Route::get('unit-conversions', [App\Http\Controllers\Admin\UnitConversionController::class, 'index'])->name('unit-conversions.index');
    Route::post('unit-conversions', [App\Http\Controllers\Admin\UnitConversionController::class, 'store'])->name('unit-conversions.store');
    Route::put('unit-conversions/{unitConversion}', [App\Http\Controllers\Admin\UnitConversionController::class, 'update'])->name('unit-conversions.update');
    Route::delete('unit-conversions/{unitConversion}', [App\Http\Controllers\Admin\UnitConversionController::class, 'destroy'])->name('unit-conversions.destroy');
    Route::get('unit-conversions/dropdown', [App\Http\Controllers\Admin\UnitConversionController::class, 'dropdown'])->name('unit-conversions.dropdown');
    
    // Report Delivery
    Route::get('report-delivery', [App\Http\Controllers\Admin\ReportDeliveryController::class, 'index'])->name('report-delivery.index');
    Route::post('report-delivery', [App\Http\Controllers\Admin\ReportDeliveryController::class, 'store'])->name('report-delivery.store');
    Route::put('report-delivery/{reportSchedule}', [App\Http\Controllers\Admin\ReportDeliveryController::class, 'update'])->name('report-delivery.update');
    Route::delete('report-delivery/{reportSchedule}', [App\Http\Controllers\Admin\ReportDeliveryController::class, 'destroy'])->name('report-delivery.destroy');
    Route::post('report-delivery/{reportSchedule}/toggle', [App\Http\Controllers\Admin\ReportDeliveryController::class, 'toggle'])->name('report-delivery.toggle');
    Route::post('report-delivery/{reportSchedule}/test', [App\Http\Controllers\Admin\ReportDeliveryController::class, 'sendTest'])->name('report-delivery.test');
    Route::post('report-delivery/send-now', [App\Http\Controllers\Admin\ReportDeliveryController::class, 'sendNow'])->name('report-delivery.send-now');
});

// Operational Routes (Accessible by Operators if assigned, and Admins)
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // User & Group Management - accessible to users with permissions or admins
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    Route::resource('groups', App\Http\Controllers\Admin\GroupController::class);
    
    Route::get('configuration', [App\Http\Controllers\Admin\ConfigurationController::class, 'index'])->name('configuration');
    
    // Production Shifts (Start/End)
    Route::post('production-shifts/create', [App\Http\Controllers\Api\ProductionShiftController::class, 'store'])->name('production-shifts.create');
    Route::get('production-shifts/active', [App\Http\Controllers\Api\ProductionShiftController::class, 'activeShifts'])->name('production-shifts.active');
    Route::get('production-shifts/{machineId}', [App\Http\Controllers\Api\ProductionShiftController::class, 'show'])->name('production-shifts.show');
    Route::get('production-shifts/{machineId}/history', [App\Http\Controllers\Api\ProductionShiftController::class, 'history'])->name('production-shifts.history');
    Route::post('production-shifts/{machineId}/start', [App\Http\Controllers\Api\ProductionShiftController::class, 'start'])->name('production-shifts.start');
    Route::post('production-shifts/{machineId}/end', [App\Http\Controllers\Api\ProductionShiftController::class, 'end'])->name('production-shifts.end');
    Route::post('production-shifts/{machineId}/downtime', [App\Http\Controllers\Api\ProductionShiftController::class, 'logDowntime'])->name('production-shifts.downtime');
    Route::post('production-shifts/shift/{shiftId}/changeover', [App\Http\Controllers\Api\ProductionShiftController::class, 'recordChangeover'])->name('production-shifts.changeover');
    Route::get('production-shifts/{machineId}/product-runs', [App\Http\Controllers\Api\ProductionShiftController::class, 'getProductRuns'])->name('production-shifts.product-runs');
    
    // Users API (for dropdowns)
    Route::get('/api/users', [App\Http\Controllers\Api\UserController::class, 'index'])->name('api.users');
    
    // Edit shift reports (restricted to supervisors/admins)
    Route::put('production-shifts/shift/{shiftId}', [App\Http\Controllers\Api\ProductionShiftController::class, 'update'])->name('production-shifts.update');
    Route::get('production-shifts/shift/{shiftId}/edit-history', [App\Http\Controllers\Api\ProductionShiftController::class, 'editHistory'])->name('production-shifts.edit-history');
    
    // Reports
    Route::get('reports', [App\Http\Controllers\Reports\OeeReportController::class, 'index'])->name('reports.index');
    Route::post('reports/generate', [App\Http\Controllers\Reports\OeeReportController::class, 'generate'])->name('reports.generate');
    Route::post('reports/export/pdf', [App\Http\Controllers\Reports\OeeReportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::post('reports/export/excel', [App\Http\Controllers\Reports\OeeReportController::class, 'exportExcel'])->name('reports.export.excel');
    Route::post('reports/export/csv', [App\Http\Controllers\Reports\OeeReportController::class, 'exportCsv'])->name('reports.export.csv');
    Route::post('reports/send-email', [App\Http\Controllers\Reports\OeeReportController::class, 'sendEmail'])->name('reports.send-email');
    
    // Yearly Reports
    Route::post('reports/yearly/generate', [App\Http\Controllers\Reports\OeeReportController::class, 'generateYearlyReport'])->name('reports.yearly.generate');
    Route::post('reports/yearly/export/excel', [App\Http\Controllers\Reports\OeeReportController::class, 'exportYearlyExcel'])->name('reports.yearly.export.excel');
    
    // Comparison Reports
    Route::get('reports/comparison', [App\Http\Controllers\Reports\ComparisonReportController::class, 'index'])->name('reports.comparison');
    Route::post('reports/comparison/period', [App\Http\Controllers\Reports\ComparisonReportController::class, 'comparePeriods'])->name('reports.comparison.period');
    Route::post('reports/comparison/machines', [App\Http\Controllers\Reports\ComparisonReportController::class, 'compareMachines'])->name('reports.comparison.machines');
    Route::post('reports/comparison/shifts', [App\Http\Controllers\Reports\ComparisonReportController::class, 'compareShifts'])->name('reports.comparison.shifts');
});

// Tickets (available to all authenticated users)
Route::middleware(['auth', 'verified'])->prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', [App\Http\Controllers\TicketController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\TicketController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\TicketController::class, 'store'])->name('store');
    Route::get('/{ticket}', [App\Http\Controllers\TicketController::class, 'show'])->name('show');
    Route::put('/{ticket}', [App\Http\Controllers\TicketController::class, 'update'])->name('update');
    Route::post('/{ticket}/comment', [App\Http\Controllers\TicketController::class, 'addComment'])->name('comment');
});

// API Notifications Route
Route::middleware(['auth', 'verified'])->prefix('api')->name('api.')->group(function() {
    Route::get('/notifications', function() {
        // If the request does not expect JSON (e.g., Inertia page load), redirect to dashboard
        if (!request()->expectsJson()) {
            return redirect()->route('dashboard');
        }
        
        return response()->json([
            'notifications' => \App\Services\NotificationService::getUnread(Auth::user()),
        ]);
    })->name('index');
    
    Route::post('/notifications/mark-read/{notification}', function($notificationId) {
        // Mark as read logic
        $notification = auth()->user()->notifications()->find($notificationId);
        if ($notification) {
            $notification->markAsRead();
        }
        return response()->json(['success' => true]);
    })->name('mark-read');
    
    Route::post('/notifications/mark-all-read', function() {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    })->name('mark-all-read');
});

// Dashboard OEE Data (API endpoint for Vue component)
Route::middleware(['auth', 'verified'])->get('/api/dashboard/oee', [App\Http\Controllers\Api\OeeDashboardController::class, 'index'])->name('api.dashboard.oee');

// Material Loss Routes
Route::middleware(['auth', 'verified'])->prefix('api/material-loss')->name('api.material-loss.')->group(function () {
    Route::get('/categories', [App\Http\Controllers\MaterialLossController::class, 'apiCategories'])->name('categories');
    Route::post('/', [App\Http\Controllers\MaterialLossController::class, 'batchStore'])->name('store');
    Route::get('/history', [App\Http\Controllers\MaterialLossController::class, 'summary'])->name('history');
});

// Andon / Alert System Routes
Route::middleware(['auth', 'verified'])->prefix('api/v1/andon')->name('api.andon.')->group(function () {
    Route::get('/status', [App\Http\Controllers\Api\AndonController::class, 'status'])->name('status');
    Route::get('/alerts', [App\Http\Controllers\Api\AndonController::class, 'alerts'])->name('alerts');
    Route::post('/alerts/{alert}/acknowledge', [App\Http\Controllers\Api\AndonController::class, 'acknowledgeAlert'])->name('alerts.acknowledge');
    Route::post('/alerts/{alert}/resolve', [App\Http\Controllers\Api\AndonController::class, 'resolveAlert'])->name('alerts.resolve');
    Route::get('/rules', [App\Http\Controllers\Api\AndonController::class, 'rules'])->name('rules');
    Route::post('/rules', [App\Http\Controllers\Api\AndonController::class, 'storeRule'])->name('rules.store');
    Route::put('/rules/{rule}', [App\Http\Controllers\Api\AndonController::class, 'updateRule'])->name('rules.update');
    Route::delete('/rules/{rule}', [App\Http\Controllers\Api\AndonController::class, 'destroyRule'])->name('rules.destroy');
});

require __DIR__.'/settings.php';

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OeeDashboardController;
use App\Http\Controllers\Api\ProductionShiftController;
use App\Http\Controllers\Api\MaintenanceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/dashboard/metrics', [OeeDashboardController::class, 'metrics']);
    Route::get('/dashboard/downtime', [OeeDashboardController::class, 'downtime']);
    Route::get('/dashboard/options', [OeeDashboardController::class, 'options']);
    Route::get('/dashboard/report', [OeeDashboardController::class, 'report']);
    
    // Advanced Analytics
    Route::get('/analytics/loss', [App\Http\Controllers\Api\AdvancedAnalyticsController::class, 'lossAnalysis']);
    Route::get('/analytics/cycle-time', [App\Http\Controllers\Api\AdvancedAnalyticsController::class, 'cycleTime']);

    // Live Data Ingestion
    Route::post('/ingest/production', [App\Http\Controllers\Api\IngestionController::class, 'production']);
    Route::post('/ingest/downtime', [App\Http\Controllers\Api\IngestionController::class, 'downtime']);
    
    // Production Shifts (require authentication via web session)
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/production-shifts/active', [ProductionShiftController::class, 'activeShifts']);
        Route::get('/production-shifts/{machineId}', [ProductionShiftController::class, 'show']);
        Route::post('/production-shifts/{machineId}/start', [ProductionShiftController::class, 'start']);
        Route::post('/production-shifts/{machineId}/end', [ProductionShiftController::class, 'end']);
        Route::post('/production-shifts/shift/{shiftId}/changeover', [ProductionShiftController::class, 'recordChangeover']);
        Route::get('/production-shifts/{shiftId}/activity', [ProductionShiftController::class, 'getActivity']);
        
        // Material Loss
        Route::get('/material-loss/categories', [App\Http\Controllers\MaterialLossController::class, 'apiCategories']);
        Route::post('/material-loss', [App\Http\Controllers\MaterialLossController::class, 'apiStore']);

    // ========== MAINTENANCE ROUTES ==========
    
    // Health Dashboard
    Route::get('/machines/{machineId}/health', [MaintenanceController::class, 'getHealthDashboard']);
    
    // Maintenance Schedules
    Route::get('/machines/{machineId}/maintenance/schedules', [MaintenanceController::class, 'getSchedules']);
    Route::post('/machines/{machineId}/maintenance/schedules', [MaintenanceController::class, 'createSchedule']);
    Route::get('/machines/{machineId}/maintenance/overdue', [MaintenanceController::class, 'getOverdue']);
    Route::get('/machines/{machineId}/maintenance/upcoming', [MaintenanceController::class, 'getUpcoming']);
    Route::put('/maintenance/schedules/{scheduleId}', [MaintenanceController::class, 'updateSchedule']);
    Route::delete('/maintenance/schedules/{scheduleId}', [MaintenanceController::class, 'deleteSchedule']);
    
    // Maintenance Logs
    Route::get('/machines/{machineId}/maintenance/logs', [MaintenanceController::class, 'getHistory']);
    Route::post('/machines/{machineId}/maintenance/logs', [MaintenanceController::class, 'logMaintenance']);
    Route::get('/maintenance/logs/{logId}', [MaintenanceController::class, 'getLog']);
    
    // Machine Components
    Route::get('/machines/{machineId}/components', [MaintenanceController::class, 'getComponents']);
    Route::get('/machines/{machineId}/components/health', [MaintenanceController::class, 'getComponentHealth']);
    Route::post('/machines/{machineId}/components', [MaintenanceController::class, 'addComponent']);
    Route::post('/machines/{machineId}/components/sync-runtime', [MaintenanceController::class, 'syncComponentRuntime']);
    Route::get('/machines/{machineId}/maintenance/export/schedules/pdf', [MaintenanceController::class, 'exportSchedulesPdf']);
    Route::get('/machines/{machineId}/maintenance/export/schedules/excel', [MaintenanceController::class, 'exportSchedulesExcel']);
    Route::get('/machines/{machineId}/maintenance/export/history', [MaintenanceController::class, 'exportHistory']);
    Route::put('/components/{componentId}', [MaintenanceController::class, 'updateComponent']);
    Route::delete('/components/{componentId}', [MaintenanceController::class, 'deleteComponent']);

    // Spare Parts Inventory
    Route::get('/spare-parts', [MaintenanceController::class, 'getSpareParts']);
    Route::get('/spare-parts/low-stock', [MaintenanceController::class, 'getLowStockParts']);
    Route::get('/spare-parts/categories', [MaintenanceController::class, 'getPartCategories']);
    Route::get('/machines/{machineId}/spare-parts', [MaintenanceController::class, 'getSpareParts']);
    Route::post('/machines/{machineId}/spare-parts', [MaintenanceController::class, 'createSparePart']);
    Route::post('/spare-parts', [MaintenanceController::class, 'createSparePart']);
    Route::put('/spare-parts/{partId}', [MaintenanceController::class, 'updateSparePart']);
    Route::delete('/spare-parts/{partId}', [MaintenanceController::class, 'deleteSparePart']);
    Route::post('/spare-parts/{partId}/adjust-stock', [MaintenanceController::class, 'adjustStock']);
    Route::get('/spare-parts/{partId}/usage-history', [MaintenanceController::class, 'getPartUsageHistory']);
    Route::post('/machines/{machineId}/spare-parts/usage', [MaintenanceController::class, 'recordPartUsage']);

    // Calendar Integration
    Route::get('/machines/{machineId}/maintenance/calendar', [MaintenanceController::class, 'getCalendarEvents']);
    Route::get('/machines/{machineId}/maintenance/export/ical', [MaintenanceController::class, 'exportIcal']);
    Route::patch('/maintenance/schedules/{scheduleId}/reschedule', [MaintenanceController::class, 'rescheduleTask']);
    });
});

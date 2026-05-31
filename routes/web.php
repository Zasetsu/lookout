<?php

use Illuminate\Support\Facades\Route;
use Zasetsu\Lookout\Http\Controllers\Dashboard\DashboardController;

if (! config('lookout.enabled', true) || ! config('lookout.dashboard.enabled', false)) {
    return;
}

Route::prefix(config('lookout.dashboard.path', 'lookout'))
    ->middleware(config('lookout.dashboard.middleware', ['web']))
    ->group(function () {
        Route::get('/', [DashboardController::class, 'overview'])->name('lookout.overview');

        Route::get('/requests', [DashboardController::class, 'requests'])->name('lookout.requests');
        Route::get('/requests/{traceId}', [DashboardController::class, 'requestDetail'])->name('lookout.request-detail');

        Route::get('/exceptions', [DashboardController::class, 'exceptions'])->name('lookout.exceptions');
        Route::get('/exceptions/{groupId}', [DashboardController::class, 'exceptionDetail'])->whereNumber('groupId')->name('lookout.exception-detail');
        Route::post('/exceptions/{groupId}/resolve', [DashboardController::class, 'resolveException'])->whereNumber('groupId')->name('lookout.exception-resolve');
        Route::post('/exceptions/{groupId}/ignore', [DashboardController::class, 'ignoreException'])->whereNumber('groupId')->name('lookout.exception-ignore');

        Route::get('/queries', [DashboardController::class, 'queries'])->name('lookout.queries');
        Route::get('/jobs', [DashboardController::class, 'jobs'])->name('lookout.jobs');
        Route::get('/scheduled', [DashboardController::class, 'scheduled'])->name('lookout.scheduled');
        Route::get('/commands', [DashboardController::class, 'commands'])->name('lookout.commands');
        Route::get('/cache', [DashboardController::class, 'cache'])->name('lookout.cache');
        Route::get('/mail', [DashboardController::class, 'mail'])->name('lookout.mail');
        Route::get('/notifications', [DashboardController::class, 'notifications'])->name('lookout.notifications');
        Route::get('/logs', [DashboardController::class, 'logs'])->name('lookout.logs');
        Route::get('/outgoing', [DashboardController::class, 'outgoing'])->name('lookout.outgoing');
        Route::get('/alerts', [DashboardController::class, 'alerts'])->name('lookout.alerts');
        Route::get('/alerts/rules/create', [DashboardController::class, 'createThresholdRule'])->name('lookout.alerts.rules.create');
        Route::post('/alerts/rules', [DashboardController::class, 'storeThresholdRule'])->name('lookout.alerts.rules.store');
        Route::get('/alerts/rules/{ruleId}/edit', [DashboardController::class, 'editThresholdRule'])->whereNumber('ruleId')->name('lookout.alerts.rules.edit');
        Route::match(['put', 'patch'], '/alerts/rules/{ruleId}', [DashboardController::class, 'updateThresholdRule'])->whereNumber('ruleId')->name('lookout.alerts.rules.update');
        Route::post('/alerts/rules/{ruleId}/toggle', [DashboardController::class, 'toggleThresholdRule'])->whereNumber('ruleId')->name('lookout.alerts.rules.toggle');
        Route::delete('/alerts/rules/{ruleId}', [DashboardController::class, 'deleteThresholdRule'])->whereNumber('ruleId')->name('lookout.alerts.rules.delete');
        Route::post('/alerts/rules/{ruleId}/evaluate', [DashboardController::class, 'evaluateThresholdRule'])->whereNumber('ruleId')->name('lookout.alerts.rules.evaluate');
        Route::post('/alerts/rules/{ruleId}/dispatch', [DashboardController::class, 'dispatchThresholdRule'])->whereNumber('ruleId')->name('lookout.alerts.rules.dispatch');
        Route::post('/alerts/channels/{channel}/test', [DashboardController::class, 'testAlertChannel'])->whereIn('channel', ['email', 'slack', 'webhook'])->name('lookout.alerts.channels.test');
        Route::get('/audit', [DashboardController::class, 'audit'])->name('lookout.audit');
        Route::get('/audit/export', [DashboardController::class, 'exportAudit'])->name('lookout.audit-export');
        Route::get('/health', [DashboardController::class, 'health'])->name('lookout.health');
    });

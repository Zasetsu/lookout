<?php

use Illuminate\Support\Facades\Route;
use Zasetsu\Lookout\Http\Controllers\Api\ApiController;

if (! config('lookout.enabled', true) || ! config('lookout.api.enabled', false)) {
    return;
}

Route::prefix(config('lookout.dashboard.path', 'lookout').'/api')
    ->middleware(['api'])
    ->group(function () {
        Route::get('/health', [ApiController::class, 'health']);
        Route::get('/summary', [ApiController::class, 'summary']);
        Route::get('/exceptions', [ApiController::class, 'exceptions']);
        Route::get('/requests', [ApiController::class, 'requests']);
        Route::get('/traces/{traceId}', [ApiController::class, 'trace']);
    });

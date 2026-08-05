<?php

use App\Http\Controllers\RouteTracking\RouteTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Tracking Routes (Planned vs Actual)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('route-tracking')->name('route-tracking.')->group(function () {
    Route::get('/', [RouteTrackingController::class, 'index'])->name('index');
    Route::get('/companies.json', [RouteTrackingController::class, 'companies'])->name('companies');
    Route::get('/areas.json', [RouteTrackingController::class, 'areas'])->name('areas');
    Route::get('/subareas.json', [RouteTrackingController::class, 'subareas'])->name('subareas');
    Route::get('/routes.json', [RouteTrackingController::class, 'routes'])->name('routes');
    Route::get('/actual-route.json', [RouteTrackingController::class, 'actualRoute'])->name('actual-route');
    Route::get('/planned-route.json', [RouteTrackingController::class, 'plannedRoute'])->name('planned-route');
    Route::get('/compare.json', [RouteTrackingController::class, 'compare'])->name('compare');
});

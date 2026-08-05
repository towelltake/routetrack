<?php

use App\Http\Controllers\RouteLocation\RouteLocationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Location Routes (last known GPS position per route/date)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('route-location')->name('route-location.')->group(function () {
    Route::get('/', [RouteLocationController::class, 'index'])->name('index');
    Route::get('/companies.json', [RouteLocationController::class, 'companies'])->name('companies');
    Route::get('/areas.json', [RouteLocationController::class, 'areas'])->name('areas');
    Route::get('/subareas.json', [RouteLocationController::class, 'subareas'])->name('subareas');
    Route::get('/last-locations.json', [RouteLocationController::class, 'lastLocations'])->name('last-locations');
});

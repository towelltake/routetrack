<?php

use App\Http\Controllers\CustomerLocation\CustomerLocationController;
use App\Http\Controllers\CustomerLocation\OsrmRouteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Location Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('customer-location')->name('customer-location.')->group(function () {
    Route::get('/', [CustomerLocationController::class, 'index'])->name('index');
    Route::get('/companies.json', [CustomerLocationController::class, 'companies'])->name('companies');
    Route::get('/areas.json', [CustomerLocationController::class, 'areas'])->name('areas');
    Route::get('/subareas.json', [CustomerLocationController::class, 'subareas'])->name('subareas');
    Route::get('/routes.json', [CustomerLocationController::class, 'routes'])->name('routes');
    Route::get('/locations.json', [CustomerLocationController::class, 'locations'])->name('locations');
    Route::get('/route.json', [OsrmRouteController::class, 'route'])->name('route');
});

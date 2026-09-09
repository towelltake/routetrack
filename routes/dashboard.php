<?php

use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes (last known GPS position per route/date)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/filters.json', [DashboardController::class, 'filters'])->name('filters');
    Route::get('/metrics.json', [DashboardController::class, 'metrics'])->name('metrics');
    Route::get('/route-status.json', [DashboardController::class, 'routeStatus'])->name('route-status');
    Route::get('/customer-details.json', [DashboardController::class, 'customerDetails'])->name('customer-details');
    Route::get('/companies.json', [DashboardController::class, 'companies'])->name('companies');
    Route::get('/routes.json', [DashboardController::class, 'routes'])->name('routes');
    Route::get('/last-locations.json', [DashboardController::class, 'lastLocations'])->name('last-locations');
});

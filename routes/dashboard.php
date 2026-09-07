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
    Route::get('/companies.json', [DashboardController::class, 'companies'])->name('companies');
    Route::get('/routes.json', [DashboardController::class, 'routes'])->name('routes');
    Route::get('/last-locations.json', [DashboardController::class, 'lastLocations'])->name('last-locations');
});

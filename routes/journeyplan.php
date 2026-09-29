<?php

use App\Http\Controllers\JourneyPlan\JourneyPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('journey-plan')->name('journey-plan.')->group(function () {
    Route::get('/', [JourneyPlanController::class, 'index'])->name('index');
    Route::get('/routes.json', [JourneyPlanController::class, 'routes'])->name('routes');
    Route::get('/plan.json', [JourneyPlanController::class, 'plan'])->name('plan');
});

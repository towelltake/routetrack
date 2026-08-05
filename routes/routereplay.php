<?php

use App\Http\Controllers\RouteReplay\RouteReplayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Replay Routes (animated playback of real GPS history)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('route-replay')->name('route-replay.')->group(function () {
    Route::get('/', [RouteReplayController::class, 'index'])->name('index');
    Route::get('/companies.json', [RouteReplayController::class, 'companies'])->name('companies');
    Route::get('/areas.json', [RouteReplayController::class, 'areas'])->name('areas');
    Route::get('/subareas.json', [RouteReplayController::class, 'subareas'])->name('subareas');
    Route::get('/routes.json', [RouteReplayController::class, 'routes'])->name('routes');
    Route::get('/track.json', [RouteReplayController::class, 'track'])->name('track');
});

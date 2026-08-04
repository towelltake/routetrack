<?php

use App\Http\Controllers\Api\IndexController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('index')->group(function () {
    Route::post('salesmanlogin/{tail?}', [IndexController::class, 'salesmanLogin'])->where('tail', '.*');
    Route::post('companyidbydevice/{tail?}', [IndexController::class, 'companyIdByDevice'])->where('tail', '.*');
    Route::get('getsyncdata/{tail?}', [IndexController::class, 'getSyncData'])->where('tail', '.*');
    Route::get('getsyncfulldata/{tail?}', [IndexController::class, 'getSyncFullData'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'updatesyncdate/{tail?}', [IndexController::class, 'updateSyncDate'])->where('tail', '.*');
});

Route::prefix('customer')->group(function () {
    Route::match(['GET', 'POST'], 'customermaster/{tail?}', [CustomerController::class, 'customerMaster'])->where('tail', '.*');
});

Route::prefix('transaction')->group(function () {
    Route::get('trandata/{tail?}', [TransactionController::class, 'tranData'])->where('tail', '.*');
});

Route::prefix('ws')->group(function () {
    Route::match(['GET', 'POST'], 'senddata/{tail?}', [WsController::class, 'sendData'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'endday/{tail?}', [WsController::class, 'endDay'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'logout/{tail?}', [WsController::class, 'logout'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'checkload/{tail?}', [WsController::class, 'checkLoad'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'routetrackl12/{tail?}', [WsController::class, 'routeTrackL12'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'getdelivery/{tail?}', [WsController::class, 'getDelivery'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'getwhstock/{tail?}', [WsController::class, 'getWhStock'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'getcustomerbalance/{tail?}', [WsController::class, 'getCustomerBalance'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'getroutebalance/{tail?}', [WsController::class, 'getRouteBalance'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'getwarehousestock/{tail?}', [WsController::class, 'getWarehouseStock'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'getsupervisorfoc/{tail?}', [WsController::class, 'getSupervisorFoc'])->where('tail', '.*');
    Route::match(['GET', 'POST'], 'updatesupervisorfoc/{tail?}', [WsController::class, 'updateSupervisorFoc'])->where('tail', '.*');
});

Route::prefix('sync')->group(function () {
    Route::match(['GET', 'POST'], 'senddata/{tail?}', [SyncController::class, 'sendData'])->where('tail', '.*');
});

Route::prefix('image')->group(function () {
    Route::post('upload', [ImageController::class, 'upload']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

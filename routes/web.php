<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/customer-location');

require __DIR__.'/auth.php';
require __DIR__.'/customerlocation.php';
require __DIR__.'/routelocation.php';
require __DIR__.'/routetracking.php';

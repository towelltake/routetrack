<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/customerlocation.php';
require __DIR__.'/dashboard.php';
require __DIR__.'/routetracking.php';

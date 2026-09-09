<?php

use Illuminate\Support\Facades\Route;

// Catch-all route to serve the Vue 3 Single Page Application
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

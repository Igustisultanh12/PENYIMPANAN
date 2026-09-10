<?php

use Illuminate\Support\Facades\Route;

// Route for login to satisfy any route('login') redirects gracefully
Route::get('/login', function () {
    return view('app');
})->name('login');

// Catch-all route to serve the Vue 3 Single Page Application
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

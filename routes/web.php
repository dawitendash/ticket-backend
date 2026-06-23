<?php

use Illuminate\Support\Facades\Route;
 
Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Unauthenticated. Please log in first.',
        'error_code' => 'unauthenticated'
    ], 401);
})->name('login');
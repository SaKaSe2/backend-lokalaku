<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocationController;

// Route untuk menyimpan lokasi
Route::post('/locations', [LocationController::class, 'store']);
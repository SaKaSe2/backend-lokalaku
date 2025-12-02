<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocationController;

Route::apiResource('locations', LocationController::class);
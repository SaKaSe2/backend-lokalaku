<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

// Public routes
Route::post('/register/user', function (Request $request) {
    $request->validate([
        'fullname' => 'required|string',
        'username' => 'required|string|unique:users',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:6',
    ]);

    $user = User::create([
        'fullname' => $request->fullname,
        'username' => $request->username,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'buyer'  // 👈 ROLE USER
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Register berhasil',
        'user' => $user,
        'token' => $token
    ], 201);
});

// Register sebagai SELLER (pedagang)
Route::post('/register/seller', function (Request $request) {
    $request->validate([
        'fullname' => 'required|string',
        'username' => 'required|string|unique:users',
        'email' => 'required|email|unique:users',
        'password' => 'required|string|min:6',
    ]);

    $user = User::create([
        'fullname' => $request->fullname,
        'username' => $request->username,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'seller'  // 👈 ROLE SELLER
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Register berhasil',
        'user' => $user,
        'token' => $token
    ], 201);
});

Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Login gagal, cek email atau password.'],
        ]);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login berhasil',
        'user' => $user,
        'token' => $token
    ]);
});

// Protected routes with custom auth check
Route::middleware(['auth:sanctum', 'api'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Seller routes
    Route::post('/seller/setup', [SellerController::class, 'storeShop']);
    Route::get('/seller/dashboard', [SellerController::class, 'getDashboard']);
    Route::post('/seller/status', [SellerController::class, 'updateStatus']);
    Route::post('/seller/menu', [SellerController::class, 'addMenu']);
    Route::get('/seller/ai-insight', [SellerController::class, 'getAiInsight']);

    // User routes
    Route::get('/user/map', [UserController::class, 'getMapData']);
    Route::get('/user/shop/{shopId}', [UserController::class, 'getShopDetail']);
});

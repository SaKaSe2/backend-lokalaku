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
        'role' => 'buyer'
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Register berhasil',
        'user' => $user,
        'token' => $token
    ], 201);
});

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
        'role' => 'seller'
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Register berhasil',
        'user' => $user,
        'token' => $token
    ], 201);
});

// ✅ FIXED: Login dengan role buyer/seller
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'role' => 'required|in:buyer,seller', // 👈 Validasi role
    ]);

    $user = User::where('email', $request->email)
                ->where('role', $request->role) // 👈 Filter berdasarkan role
                ->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Login gagal. Email, password, atau role tidak sesuai.'],
        ]);
    }

    // Hapus token lama (opsional, untuk keamanan)
    $user->tokens()->delete();

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login berhasil',
        'user' => [
            'id' => $user->id,
            'fullname' => $user->fullname,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ],
        'token' => $token
    ]);
});

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {

    // Get current user info
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'fullname' => $user->fullname,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    });

    // ✅ FIXED: GET Cek Data Seller (sekarang berfungsi)
    Route::get('/seller', [SellerController::class, 'getDashboard']);

    // Seller routes
    Route::post('/seller/setup', [SellerController::class, 'storeShop']);
    Route::post('/seller/status', [SellerController::class, 'updateStatus']);
    Route::post('/seller/menu', [SellerController::class, 'addMenu']);
    Route::put('/seller/menu/{menuId}', [SellerController::class, 'updateMenu']);
    Route::delete('/seller/menu/{menuId}', [SellerController::class, 'deleteMenu']);
    Route::get('/seller/ai-insight', [SellerController::class, 'getAiInsight']);

    // User/Buyer routes
    Route::get('/user/map', [UserController::class, 'getMapData']);
    Route::get('/user/shop/{shopId}', [UserController::class, 'getShopDetail']);

    // Admin/List routes (bisa diakses buyer & seller)
    Route::get('/users', [App\Http\Controllers\Api\AdminController::class, 'getAllUsers']);
    Route::get('/users/buyers', [App\Http\Controllers\Api\AdminController::class, 'getAllBuyers']);
    Route::get('/users/sellers', [App\Http\Controllers\Api\AdminController::class, 'getAllSellers']);
    Route::get('/users/sellers/shops', [App\Http\Controllers\Api\AdminController::class, 'getAllSellersWithShops']);
    Route::get('/users/stats', [App\Http\Controllers\Api\AdminController::class, 'getUserStats']);
    Route::get('/users/{userId}', [App\Http\Controllers\Api\AdminController::class, 'getUserDetail']);
});

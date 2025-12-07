<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BuyerController;
use App\Http\Controllers\Api\SellerController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

// ==========================================
// PUBLIC ROUTES (Tidak perlu auth)
// ==========================================

// Register Buyer
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
        'user' => [
            'id' => $user->id,
            'fullname' => $user->fullname,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ],
        'token' => $token
    ], 201);
});

// Register Seller
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
        'user' => [
            'id' => $user->id,
            'fullname' => $user->fullname,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ],
        'token' => $token
    ], 201);
});

// Login (Buyer & Seller)
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'role' => 'required|in:buyer,seller',
    ]);

    $user = User::where('email', $request->email)
        ->where('role', $request->role)
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

// ==========================================
// PROTECTED ROUTES (Perlu auth:sanctum)
// ==========================================

Route::middleware(['auth:sanctum'])->group(function () {

    // ==========================================
    // USER ROUTES (Umum untuk semua role)
    // ==========================================

    // Get current user info
    Route::get('/user', [UserController::class, 'getProfile']);

    // Update user profile
    Route::put('/user/profile', [UserController::class, 'updateProfile']);

    // ==========================================
    // BUYER ROUTES
    // ==========================================

    Route::prefix('buyer')->group(function () {
        // Get map data (toko terdekat + cuaca + AI recommendation)
        Route::post('/map', [BuyerController::class, 'getMapData']);

        // Get detail toko beserta menu
        Route::get('/shop/{shopId}', [BuyerController::class, 'getShopDetail']);

        Route::get('/shops', [BuyerController::class, 'getAllShops']);
        Route::get('/shops/simple', [BuyerController::class, 'getAllShopsSimple']); // Alternatif sederhana
        Route::get('/shops/categories', [BuyerController::class, 'getShopCategories']);
        Route::get('/shops/statistics', [BuyerController::class, 'getShopStatistics']);
        // Route::get('/shops/{shopId}', [BuyerController::class, 'getShopDetail']);

        // Route yang sudah ada
Route::get('/map-data', [BuyerController::class, 'getMapData']);
    });

    // ==========================================
    // SELLER ROUTES
    // ==========================================

    Route::prefix('seller')->group(function () {
        // Dashboard - Cek data lapak
        Route::get('/dashboard', [SellerController::class, 'getDashboard']);

        // Setup/Update data lapak
        Route::post('/setup', [SellerController::class, 'storeShop']);

        // Update status live (ON/OFF)
        Route::post('/status', [SellerController::class, 'updateStatus']);

        // Menu Management
        Route::post('/menu', [SellerController::class, 'addMenu']);
        Route::put('/menu/{menuId}', [SellerController::class, 'updateMenu']);
        Route::delete('/menu/{menuId}', [SellerController::class, 'deleteMenu']);

        // AI Features
        Route::get('/ai-insight', [SellerController::class, 'getAiInsight']);
        Route::get('/market-analysis', [SellerController::class, 'getMarketAnalysis']);
    });

    // ==========================================
    // ADMIN ROUTES (Optional - jika ada AdminController)
    // ==========================================

    Route::prefix('admin')->group(function () {
        Route::get('/users', [App\Http\Controllers\Api\AdminController::class, 'getAllUsers']);
        Route::get('/buyers', [App\Http\Controllers\Api\AdminController::class, 'getAllBuyers']);
        Route::get('/sellers', [App\Http\Controllers\Api\AdminController::class, 'getAllSellers']);
        Route::get('/sellers/shops', [App\Http\Controllers\Api\AdminController::class, 'getAllSellersWithShops']);
        Route::get('/stats', [App\Http\Controllers\Api\AdminController::class, 'getUserStats']);
        Route::get('/users/{userId}', [App\Http\Controllers\Api\AdminController::class, 'getUserDetail']);
    });

    // ==========================================
    // LOGOUT
    // ==========================================

    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    });
});

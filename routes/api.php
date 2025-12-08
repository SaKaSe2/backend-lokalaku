<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BuyerController;
use App\Http\Controllers\Api\SellerController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user', [UserController::class, 'getProfile']);

    Route::put('/user/profile', [UserController::class, 'updateProfile']);

    Route::prefix('buyer')->group(function () {

        Route::post('/map-data', [BuyerController::class, 'getMapData']);

        Route::get('/shop/{shopId}', [BuyerController::class, 'getShopDetail']);

        Route::get('/shops', [BuyerController::class, 'getAllShops']);
        Route::get('/shops/simple', [BuyerController::class, 'getAllShopsSimple']);
        Route::get('/shops/categories', [BuyerController::class, 'getShopCategories']);
        Route::get('/shops/statistics', [BuyerController::class, 'getShopStatistics']);

Route::get('/map-data', [BuyerController::class, 'getMapData']);
    });

    Route::prefix('seller')->group(function () {
        Route::get('/', [SellerController::class, 'getDashboard']);

        Route::get('/dashboard', [SellerController::class, 'getDashboard']);

        Route::post('/setup', [SellerController::class, 'storeShop']);

        Route::post('/status', [SellerController::class, 'updateStatus']);

        Route::post('/menu', [SellerController::class, 'addMenu']);
        Route::put('/menu/{menuId}', [SellerController::class, 'updateMenu']);
        Route::delete('/menu/{menuId}', [SellerController::class, 'deleteMenu']);

        Route::get('/ai-insight', [SellerController::class, 'getAiInsight']);
        Route::get('/market-analysis', [SellerController::class, 'getMarketAnalysis']);
    });

    Route::prefix('admin')->group(function () {
        Route::get('/users', [App\Http\Controllers\Api\AdminController::class, 'getAllUsers']);
        Route::get('/buyers', [App\Http\Controllers\Api\AdminController::class, 'getAllBuyers']);
        Route::get('/sellers', [App\Http\Controllers\Api\AdminController::class, 'getAllSellers']);
        Route::get('/sellers/shops', [App\Http\Controllers\Api\AdminController::class, 'getAllSellersWithShops']);
        Route::get('/stats', [App\Http\Controllers\Api\AdminController::class, 'getUserStats']);
        Route::get('/users/{userId}', [App\Http\Controllers\Api\AdminController::class, 'getUserDetail']);
    });

    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    });
});

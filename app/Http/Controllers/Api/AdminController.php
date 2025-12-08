<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Shop;

class AdminController extends Controller
{
    /**
     * Get all users (buyer + seller)
     */
    public function getAllUsers(Request $request)
    {
        $query = User::query();


        if ($request->has('role')) {
            $query->where('role', $request->role);
        }


        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Data users berhasil diambil',
            'total' => $users->count(),
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }

    /**
     * Get all buyers only
     */
    public function getAllBuyers(Request $request)
    {
        $query = User::where('role', 'buyer');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $buyers = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Data buyers berhasil diambil',
            'total' => $buyers->count(),
            'data' => $buyers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'username' => $user->username,
                    'email' => $user->email,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }

    /**
     * Get all sellers only
     */
    public function getAllSellers(Request $request)
    {
        $query = User::where('role', 'seller');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sellers = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Data sellers berhasil diambil',
            'total' => $sellers->count(),
            'data' => $sellers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'username' => $user->username,
                    'email' => $user->email,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }

    /**
     * Get all sellers with their shop data
     */
    public function getAllSellersWithShops(Request $request)
    {
        $query = User::with('shop.menus')
            ->where('role', 'seller');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }


        if ($request->has('is_live')) {
            $query->whereHas('shop', function($q) use ($request) {
                $q->where('is_live', $request->is_live);
            });
        }


        if ($request->has('category')) {
            $query->whereHas('shop', function($q) use ($request) {
                $q->where('category', $request->category);
            });
        }

        $sellers = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Data sellers dengan lapak berhasil diambil',
            'total' => $sellers->count(),
            'data' => $sellers->map(function ($user) {
                $shop = $user->shop;
                return [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'username' => $user->username,
                    'email' => $user->email,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'shop' => $shop ? [
                        'id' => $shop->id,
                        'name' => $shop->name,
                        'description' => $shop->description,
                        'category' => $shop->category,
                        'whatsapp_number' => $shop->whatsapp_number,
                        'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
                        'cart_image' => $shop->cart_image ? asset('storage/' . $shop->cart_image) : null,
                        'is_live' => $shop->is_live,
                        'latitude' => $shop->latitude,
                        'longitude' => $shop->longitude,
                        'total_menus' => $shop->menus->count(),
                        'menus' => $shop->menus->map(function($menu) {
                            return [
                                'id' => $menu->id,
                                'name' => $menu->name,
                                'price' => $menu->price,
                                'image' => asset('storage/' . $menu->image)
                            ];
                        })
                    ] : null
                ];
            })
        ]);
    }

    /**
     * Get user statistics
     */
    public function getUserStats()
    {
        $totalUsers = User::count();
        $totalBuyers = User::where('role', 'buyer')->count();
        $totalSellers = User::where('role', 'seller')->count();

        $sellersWithShop = User::where('role', 'seller')
            ->whereHas('shop')
            ->count();

        $sellersWithoutShop = $totalSellers - $sellersWithShop;

        $liveShops = Shop::where('is_live', true)->count();
        $offlineShops = Shop::where('is_live', false)->count();

        return response()->json([
            'message' => 'Statistik users berhasil diambil',
            'data' => [
                'total_users' => $totalUsers,
                'total_buyers' => $totalBuyers,
                'total_sellers' => $totalSellers,
                'sellers_with_shop' => $sellersWithShop,
                'sellers_without_shop' => $sellersWithoutShop,
                'live_shops' => $liveShops,
                'offline_shops' => $offlineShops,
            ]
        ]);
    }

    /**
     * Get specific user detail
     */
    public function getUserDetail($userId)
    {
        $user = User::with('shop.menus')->findOrFail($userId);

        $data = [
            'id' => $user->id,
            'fullname' => $user->fullname,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
        ];

        if ($user->role === 'seller' && $user->shop) {
            $shop = $user->shop;
            $data['shop'] = [
                'id' => $shop->id,
                'name' => $shop->name,
                'description' => $shop->description,
                'category' => $shop->category,
                'whatsapp_number' => $shop->whatsapp_number,
                'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
                'cart_image' => $shop->cart_image ? asset('storage/' . $shop->cart_image) : null,
                'is_live' => $shop->is_live,
                'latitude' => $shop->latitude,
                'longitude' => $shop->longitude,
                'total_menus' => $shop->menus->count(),
                'menus' => $shop->menus->map(function($menu) {
                    return [
                        'id' => $menu->id,
                        'name' => $menu->name,
                        'price' => $menu->price,
                        'image' => asset('storage/' . $menu->image)
                    ];
                })
            ];
        }

        return response()->json([
            'message' => 'Detail user berhasil diambil',
            'data' => $data
        ]);
    }
}

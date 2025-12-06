<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Menu;
use App\Services\KolosalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SellerController extends Controller
{
    public function storeShop(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'whatsapp_number' => 'required|string',
            'category' => 'required|string',
            'description' => 'nullable|string',
            'profile_image' => 'image|nullable|max:2048',
            'cart_image' => 'image|nullable|max:2048',
        ]);

        $data = $request->only(['name', 'whatsapp_number', 'category', 'description']);
        $data['user_id'] = Auth::id();

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('shops', 'public');
        }
        if ($request->hasFile('cart_image')) {
            $data['cart_image'] = $request->file('cart_image')->store('shops', 'public');
        }

        $shop = Shop::updateOrCreate(['user_id' => Auth::id()], $data);

        return response()->json([
            'message' => 'Data lapak berhasil disimpan!',
            'data' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'description' => $shop->description,
                'category' => $shop->category,
                'whatsapp_number' => $shop->whatsapp_number,
                'profile_image' => $shop->profile_image
                    ? config('app.url') . '/storage/' . $shop->profile_image
                    : null,

                'cart_image' => $shop->cart_image
                    ? config('app.url') . '/storage/' . $shop->cart_image
                    : null,

                'is_live' => $shop->is_live,
                'latitude' => $shop->latitude,
                'longitude' => $shop->longitude,
            ]
        ]);
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'is_live' => 'required|boolean',
            'latitude' => 'required_if:is_live,true|nullable|numeric',
            'longitude' => 'required_if:is_live,true|nullable|numeric',
        ]);

        $shop = Shop::where('user_id', Auth::id())->firstOrFail();

        $shop->update([
            'is_live' => $request->is_live,
            'latitude' => $request->is_live ? $request->latitude : null,
            'longitude' => $request->is_live ? $request->longitude : null
        ]);

        return response()->json([
            'message' => 'Status berhasil diupdate',
            'data' => [
                'is_live' => $shop->is_live,
                'latitude' => $shop->latitude,
                'longitude' => $shop->longitude,
            ]
        ]);
    }

    public function addMenu(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'image' => 'required|image|max:2048',
        ]);

        $shop = Shop::where('user_id', Auth::id())->firstOrFail();

        $imagePath = $request->file('image')->store('menus', 'public');

        $menu = $shop->menus()->create([
            'name' => $request->name,
            'price' => $request->price,
            'image' => $imagePath
        ]);

        return response()->json([
            'message' => 'Menu berhasil ditambahkan',
            'data' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'price' => $menu->price,
                'image' => config('app.url') . '/storage/' . $menu->image
            ]
        ]);
    }

    public function getAiInsight(KolosalService $aiService)
    {
        $shop = Shop::where('user_id', Auth::id())->firstOrFail();

        if (!$shop->latitude || !$shop->longitude) {
            return response()->json([
                'message' => 'Aktifkan status live terlebih dahulu untuk mendapatkan insight AI',
                'data' => null
            ], 400);
        }

        $insight = $aiService->getRecommendation(
            $shop->category,
            "{$shop->latitude}, {$shop->longitude}"
        );

        return response()->json([
            'message' => 'AI Insight berhasil didapatkan',
            'data' => $insight
        ]);
    }

    // ✅ FIXED: Endpoint GET Dashboard Seller
    public function getDashboard()
    {
        $shop = Shop::with('menus')->where('user_id', Auth::id())->first();

        if (!$shop) {
            return response()->json([
                'status' => 'empty',
                'message' => 'Belum ada data lapak. Silakan setup terlebih dahulu.',
                'data' => null
            ], 200);
        }

        return response()->json([
            'status' => 'ready',
            'message' => 'Data lapak berhasil diambil',
            'data' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'description' => $shop->description,
                'category' => $shop->category,
                'whatsapp_number' => $shop->whatsapp_number,
                'profile_image' => $shop->profile_image
                    ? config('app.url') . '/storage/' . $shop->profile_image
                    : null,

                'cart_image' => $shop->cart_image
                    ? config('app.url') . '/storage/' . $shop->cart_image
                    : null,

                'is_live' => $shop->is_live,
                'latitude' => $shop->latitude,
                'longitude' => $shop->longitude,
                'menus' => $shop->menus->map(function ($menu) {
                    return [
                        'id' => $menu->id,
                        'name' => $menu->name,
                        'price' => $menu->price,
                        'image' => config('app.url') . '/storage/' . $menu->image
                    ];
                })
            ]
        ]);
    }

    // ✅ NEW: Update menu
    public function updateMenu(Request $request, $menuId)
    {
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        $shop = Shop::where('user_id', Auth::id())->firstOrFail();
        $menu = $shop->menus()->findOrFail($menuId);

        $menu->name = $request->name;
        $menu->price = $request->price;

        if ($request->hasFile('image')) {
            // Hapus gambar lama
            if ($menu->image) {
                Storage::disk('public')->delete($menu->image);
            }
            $menu->image = $request->file('image')->store('menus', 'public');
        }

        $menu->save();

        return response()->json([
            'message' => 'Menu berhasil diupdate',
            'data' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'price' => $menu->price,
                'image' => config('app.url') . '/storage/' . $menu->image
            ]
        ]);
    }

    // ✅ NEW: Delete menu
    public function deleteMenu($menuId)
    {
        $shop = Shop::where('user_id', Auth::id())->firstOrFail();
        $menu = $shop->menus()->findOrFail($menuId);

        // Hapus gambar dari storage
        if ($menu->image) {
            Storage::disk('public')->delete($menu->image);
        }

        $menu->delete();

        return response()->json([
            'message' => 'Menu berhasil dihapus'
        ]);
    }
}

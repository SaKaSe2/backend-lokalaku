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
            'profile_image' => 'image|nullable',
            'cart_image' => 'image|nullable',
        ]);

        $data = $request->all();
        $data['user_id'] = Auth::id();

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('shops', 'public');
        }
        if ($request->hasFile('cart_image')) {
            $data['cart_image'] = $request->file('cart_image')->store('shops', 'public');
        }

        $shop = Shop::updateOrCreate(['user_id' => Auth::id()], $data);

        return response()->json(['message' => 'Data lapak berhasil disimpan!', 'data' => $shop]);
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'is_live' => 'required|boolean',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $shop = Shop::where('user_id', Auth::id())->firstOrFail();

        $shop->update([
            'is_live' => $request->is_live,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude
        ]);

        return response()->json(['message' => 'Status berhasil diupdate', 'is_live' => $shop->is_live]);
    }

    public function addMenu(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'image' => 'required|image',
        ]);

        $shop = Shop::where('user_id', Auth::id())->firstOrFail();

        $imagePath = $request->file('image')->store('menus', 'public');

        $menu = $shop->menus()->create([
            'name' => $request->name,
            'price' => $request->price,
            'image' => $imagePath
        ]);

        return response()->json(['message' => 'Menu berhasil ditambahkan', 'data' => $menu]);
    }

    public function getAiInsight(KolosalService $aiService)
    {
        $shop = Shop::where('user_id', Auth::id())->firstOrFail();
        $insight = $aiService->getRecommendation(
            $shop->category,
            "{$shop->latitude}, {$shop->longitude}"
        );

        return response()->json($insight);
    }

    public function getDashboard()
    {
        $shop = Shop::with('menus')->where('user_id', Auth::id())->first();

        if (!$shop) {
            return response()->json(['status' => 'empty', 'message' => 'Belum isi data lapak']);
        }

        return response()->json(['status' => 'ready', 'data' => $shop]);
    }
}

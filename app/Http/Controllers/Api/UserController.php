<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Services\WeatherService;
use App\Services\KolosalService;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    protected $weatherService;
    protected $kolosalService;

    public function __construct(WeatherService $weatherService, KolosalService $kolosalService)
    {
        $this->weatherService = $weatherService;
        $this->kolosalService = $kolosalService;
    }

    public function getMapData(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = 1; // 1 km

        // 1. Ambil data cuaca
        $weather = $this->weatherService->getCurrentWeather($latitude, $longitude);

        // 2. Cari pedagang dalam radius 1km yang sedang live
        $nearbyShops = $this->getNearbyShops($latitude, $longitude, $radius);

        // 3. Dapatkan rekomendasi AI dari Kolosal
        $aiRecommendation = null;
        if ($weather && count($nearbyShops) > 0) {
            $aiRecommendation = $this->kolosalService->getUserRecommendation(
                $weather,
                $latitude,
                $longitude,
                $nearbyShops
            );
        }

        return response()->json([
            'weather' => $weather,
            'nearby_shops' => $nearbyShops,
            'ai_recommendation' => $aiRecommendation
        ]);
    }

    private function getNearbyShops($latitude, $longitude, $radiusInKm)
    {
        // Haversine formula untuk menghitung jarak
        $shops = Shop::select(
            'shops.*',
            DB::raw("
                (6371 * acos(cos(radians(?))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians(?))
                + sin(radians(?))
                * sin(radians(latitude)))) AS distance
            ")
        )
            ->having('distance', '<=', $radiusInKm)
            ->where('is_live', true)
            ->orderBy('distance', 'asc')
            ->setBindings([$latitude, $longitude, $latitude])
            ->get();

        return $shops->map(function ($shop) {
            return [
                'id' => $shop->id,
                'name' => $shop->name,
                'category' => $shop->category,
                'whatsapp_number' => $shop->whatsapp_number,
                'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
                'distance' => round($shop->distance * 1000) // convert to meters
            ];
        })->toArray();
    }

    public function getShopDetail($shopId)
    {
        $shop = Shop::with('menus')->findOrFail($shopId);

        return response()->json([
            'id' => $shop->id,
            'name' => $shop->name,
            'description' => $shop->description,
            'category' => $shop->category,
            'whatsapp_number' => $shop->whatsapp_number,
            'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
            'cart_image' => $shop->cart_image ? asset('storage/' . $shop->cart_image) : null,
            'is_live' => $shop->is_live,
            'menus' => $shop->menus->map(function ($menu) {
                return [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'price' => $menu->price,
                    'image' => asset('storage/' . $menu->image)
                ];
            })
        ]);
    }
}

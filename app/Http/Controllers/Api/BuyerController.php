<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Services\WeatherService;
use App\Services\KolosalService;
use Illuminate\Support\Facades\Log;

class BuyerController extends Controller
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
        try {
            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'radius' => 'nullable|numeric|min:1|max:10',
            ]);

            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->input('radius', 1);

            // Batasi radius antara 1-10 km
            $radius = max(1, min(10, $radius));

            // Dapatkan data cuaca
            $weather = $this->weatherService->getCurrentWeather($latitude, $longitude);

            if (!$weather) {
                Log::warning('Weather API returned null', [
                    'lat' => $latitude,
                    'lon' => $longitude
                ]);

                $weather = [
                    'temperature' => 28,
                    'feels_like' => 30,
                    'description' => 'cerah',
                    'main' => 'Clear',
                    'humidity' => 70,
                    'wind_speed' => 2.5
                ];
            }

            // Dapatkan toko terdekat
            $nearbyShops = $this->getNearbyShops($latitude, $longitude, $radius);

            // Dapatkan rekomendasi AI
            $aiRecommendation = null;
            if (count($nearbyShops) > 0) {
                try {
                    $aiRecommendation = $this->kolosalService->getUserRecommendation(
                        $weather,
                        $latitude,
                        $longitude,
                        $nearbyShops
                    );

                    if (!is_array($aiRecommendation) || !isset($aiRecommendation['recommendation'])) {
                        Log::warning('AI recommendation invalid format', ['data' => $aiRecommendation]);
                        $aiRecommendation = [
                            'recommendation' => 'Es Teh Manis',
                            'reason' => 'Minuman segar untuk cuaca saat ini',
                            'shop_name' => $nearbyShops[0]['name']
                        ];
                    }
                } catch (\Exception $aiError) {
                    Log::error('AI Recommendation Error: ' . $aiError->getMessage());
                    $aiRecommendation = [
                        'recommendation' => 'Es Teh Manis',
                        'reason' => 'Minuman segar untuk cuaca saat ini',
                        'shop_name' => $nearbyShops[0]['name']
                    ];
                }
            }

            return response()->json([
                'weather' => $weather,
                'nearby_shops' => $nearbyShops,
                'ai_recommendation' => $aiRecommendation,
                'search_radius' => $radius,
                'total_shops' => count($nearbyShops)
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getMapData: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getNearbyShops($latitude, $longitude, $radiusInKm)
    {
        try {
            $shops = Shop::selectRaw("
                *,
                (6371 * acos(
                    cos(radians(?))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(latitude))
                )) AS distance
            ", [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $radiusInKm)
            ->where('is_live', true)
            ->orderBy('distance', 'asc')
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
                    'distance' => round($shop->distance * 1000), // dalam meter
                    'distance_km' => round($shop->distance, 2)    // dalam km
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Error getting nearby shops: ' . $e->getMessage());
            return [];
        }
    }

    public function getShopDetail($shopId)
    {
        try {
            // Load relasi menus DAN user (seller) - sesuai dengan relasi di Model Shop
            $shop = Shop::with(['menus', 'user'])->findOrFail($shopId);

            return response()->json([
                'id' => $shop->id,
                'name' => $shop->name,
                'description' => $shop->description,
                'category' => $shop->category,
                'whatsapp_number' => $shop->whatsapp_number,
                'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
                'cart_image' => $shop->cart_image ? asset('storage/' . $shop->cart_image) : null,
                'is_live' => $shop->is_live,
                // Info seller - disesuaikan dengan struktur User model (fullname, username, email)
                'seller' => [
                    'id' => $shop->user->id,
                    'fullname' => $shop->user->fullname,
                    'username' => $shop->user->username,
                    'email' => $shop->user->email,
                ],
                // List menu
                'menus' => $shop->menus->map(function ($menu) {
                    return [
                        'id' => $menu->id,
                        'name' => $menu->name,
                        'price' => $menu->price,
                        'image' => asset('storage/' . $menu->image)
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting shop detail: ' . $e->getMessage());

            return response()->json([
                'error' => 'Shop not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }
}

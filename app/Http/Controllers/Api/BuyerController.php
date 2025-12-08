<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Services\WeatherService;
use App\Services\KolosalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BuyerController extends Controller
{
    protected $weatherService;
    protected $kolosalService;

    public function __construct(WeatherService $weatherService, KolosalService $kolosalService)
    {
        $this->weatherService = $weatherService;
        $this->kolosalService = $kolosalService;
    }

    /**
     * Get map data with nearby shops, weather, and AI recommendations
     */
    public function getMapData(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'radius' => 'nullable|numeric|min:0.1|max:10'
            ]);

            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->get('radius', 1);

            $weather = $this->weatherService->getCurrentWeather($latitude, $longitude);

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
                ->where('is_live', true)
                ->having('distance', '<=', $radius)
                ->orderBy('distance', 'asc')
                ->with('menus')
                ->get();

            $nearbyShops = $shops->map(function ($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'description' => $shop->description,
                    'category' => $shop->category,
                    'whatsapp_number' => $shop->whatsapp_number,
                    'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
                    'cart_image' => $shop->cart_image ? asset('storage/' . $shop->cart_image) : null,
                    'latitude' => (float) $shop->latitude,
                    'longitude' => (float) $shop->longitude,
                    'distance' => round($shop->distance * 1000),
                    'distance_km' => round($shop->distance, 2),
                    'is_live' => (bool) $shop->is_live,
                    'menus' => $shop->menus->map(function ($menu) {
                        return [
                            'id' => $menu->id,
                            'name' => $menu->name,
                            'description' => $menu->description,
                            'price' => $menu->price,
                            'image' => $menu->image ? asset('storage/' . $menu->image) : null,
                        ];
                    })
                ];
            });

            $recommendation = null;
            if ($weather && $nearbyShops->isNotEmpty()) {
                try {
                    $recommendation = $this->kolosalService->getUserRecommendation(
                        $weather,
                        $latitude,
                        $longitude,
                        $nearbyShops->toArray()
                    );
                } catch (\Exception $e) {
                    Log::warning('AI Recommendation failed: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'weather' => $weather,
                    'nearby_shops' => $nearbyShops,
                    'total_shops' => $nearbyShops->count(),
                    'recommendation' => $recommendation,
                    'user_location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ],
                    'search_radius_km' => $radius
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getMapData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengambil data peta',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get shop detail by ID
     */
    public function getShopDetail($shopId)
    {
        try {
            $shop = Shop::with('menus')->findOrFail($shopId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'description' => $shop->description,
                    'category' => $shop->category,
                    'whatsapp_number' => $shop->whatsapp_number,
                    'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
                    'cart_image' => $shop->cart_image ? asset('storage/' . $shop->cart_image) : null,
                    'latitude' => (float) $shop->latitude,
                    'longitude' => (float) $shop->longitude,
                    'is_live' => (bool) $shop->is_live,
                    'menus' => $shop->menus->map(function ($menu) {
                        return [
                            'id' => $menu->id,
                            'name' => $menu->name,
                            'description' => $menu->description,
                            'price' => $menu->price,
                            'image' => $menu->image ? asset('storage/' . $menu->image) : null,
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getShopDetail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Toko tidak ditemukan'
            ], 404);
        }
    }

    /**
     * Mendapatkan semua toko
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllShops(Request $request)
    {
        try {

            $request->validate([
                'category' => 'nullable|string',
                'search' => 'nullable|string|max:100',
                'is_live' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'with_menus' => 'nullable|boolean',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'sort_by' => 'nullable|in:name,distance,created_at',
                'sort_order' => 'nullable|in:asc,desc'
            ]);

            $query = Shop::query();


            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }


            if ($request->has('is_live')) {
                $query->where('is_live', filter_var($request->is_live, FILTER_VALIDATE_BOOLEAN));
            } else {

                $query->where('is_live', true);
            }


            if ($request->has('search') && $request->search) {
                $searchTerm = '%' . $request->search . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', $searchTerm)
                        ->orWhere('description', 'LIKE', $searchTerm)
                        ->orWhere('category', 'LIKE', $searchTerm);
                });
            }


            if ($request->has('latitude') && $request->has('longitude')) {
                $latitude = $request->latitude;
                $longitude = $request->longitude;

                $query->selectRaw("
                    *,
                    (6371 * acos(
                        cos(radians(?))
                        * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(latitude))
                    )) AS distance
                ", [$latitude, $longitude, $latitude]);


                if ($request->get('sort_by') === 'distance') {
                    $sortOrder = $request->get('sort_order', 'asc');
                    $query->orderBy('distance', $sortOrder);
                } else if (!$request->has('sort_by')) {

                    $query->orderBy('distance', 'asc');
                }
            }


            if ($request->has('sort_by') && $request->sort_by !== 'distance') {
                $sortOrder = $request->get('sort_order', 'asc');
                $query->orderBy($request->sort_by, $sortOrder);
            } else if (!$request->has('latitude') || !$request->has('longitude')) {

                $query->orderBy('created_at', 'desc');
            }


            if ($request->boolean('with_menus')) {
                $query->with(['menus' => function ($query) {

                    $query->orderBy('name', 'asc');
                }]);
            }


            $perPage = $request->get('per_page', 20);
            $shops = $query->paginate($perPage);


            $shopsData = $shops->map(function ($shop) use ($request) {
                $data = [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'description' => $shop->description,
                    'category' => $shop->category,
                    'whatsapp_number' => $shop->whatsapp_number,
                    'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
                    'cart_image' => $shop->cart_image ? asset('storage/' . $shop->cart_image) : null,
                    'latitude' => (float) $shop->latitude,
                    'longitude' => (float) $shop->longitude,
                    'is_live' => (bool) $shop->is_live,
                    'created_at' => $shop->created_at->toDateTimeString(),
                    'updated_at' => $shop->updated_at->toDateTimeString(),
                ];


                if (isset($shop->distance)) {
                    $data['distance_km'] = round($shop->distance, 2);
                    $data['distance_m'] = round($shop->distance * 1000);
                }


                if ($request->boolean('with_menus') && $shop->relationLoaded('menus')) {
                    $data['menus'] = $shop->menus->map(function ($menu) {
                        return [
                            'id' => $menu->id,
                            'name' => $menu->name,
                            'description' => $menu->description,
                            'price' => $menu->price,
                            'image' => $menu->image ? asset('storage/' . $menu->image) : null,

                        ];
                    });
                }


                if (!$request->boolean('with_menus')) {

                    $data['total_menus'] = $shop->menus()->count();
                }

                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => $shopsData,
                'meta' => [
                    'current_page' => $shops->currentPage(),
                    'last_page' => $shops->lastPage(),
                    'per_page' => $shops->perPage(),
                    'total' => $shops->total(),
                    'from' => $shops->firstItem(),
                    'to' => $shops->lastItem(),
                ],
                'filters' => [
                    'category' => $request->category,
                    'search' => $request->search,
                    'is_live' => $request->has('is_live') ? filter_var($request->is_live, FILTER_VALIDATE_BOOLEAN) : true,
                    'with_menus' => $request->boolean('with_menus'),
                    'sort_by' => $request->get('sort_by', $request->has('latitude') && $request->has('longitude') ? 'distance' : 'created_at'),
                    'sort_order' => $request->get('sort_order', 'asc'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAllShops: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengambil data toko',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mendapatkan semua kategori toko yang tersedia
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getShopCategories()
    {
        try {

            $categories = Shop::where('is_live', true)
                ->distinct()
                ->pluck('category')
                ->filter()
                ->values()
                ->toArray();


            if (empty($categories)) {
                $categories = ['Makanan', 'Minuman', 'Snack', 'Lainnya'];
            }

            return response()->json([
                'success' => true,
                'data' => $categories,
                'total_categories' => count($categories)
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getShopCategories: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengambil kategori toko',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mendapatkan statistik toko
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getShopStatistics()
    {
        try {
            $totalShops = Shop::count();
            $liveShops = Shop::where('is_live', true)->count();


            $categories = Shop::select('category', DB::raw('COUNT(*) as count'))
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->groupBy('category')
                ->orderBy('count', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_shops' => $totalShops,
                    'live_shops' => $liveShops,
                    'offline_shops' => $totalShops - $liveShops,
                    'categories_distribution' => $categories,
                    'live_percentage' => $totalShops > 0 ? round(($liveShops / $totalShops) * 100, 2) : 0,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getShopStatistics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengambil statistik toko'
            ], 500);
        }
    }

    /**
     * API sederhana untuk mendapatkan semua toko tanpa filter
     * (Alternatif jika getAllShops terlalu kompleks)
     */
    public function getAllShopsSimple(Request $request)
    {
        try {
            $shops = Shop::where('is_live', true)
                ->orderBy('created_at', 'desc')
                ->get();

            $shopsData = $shops->map(function ($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'description' => $shop->description,
                    'category' => $shop->category,
                    'whatsapp_number' => $shop->whatsapp_number,
                    'profile_image' => $shop->profile_image ? asset('storage/' . $shop->profile_image) : null,
                    'cart_image' => $shop->cart_image ? asset('storage/' . $shop->cart_image) : null,
                    'latitude' => (float) $shop->latitude,
                    'longitude' => (float) $shop->longitude,
                    'is_live' => (bool) $shop->is_live,
                    'created_at' => $shop->created_at->toDateTimeString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $shopsData,
                'total' => $shops->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAllShopsSimple: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengambil data toko',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

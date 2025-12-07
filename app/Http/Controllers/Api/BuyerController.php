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

    // ... method getMapData dan getShopDetail yang sudah ada ...

    /**
     * Mendapatkan semua toko
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllShops(Request $request)
    {
        try {
            // Validasi parameter opsional
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

            // Filter berdasarkan kategori
            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            // Filter berdasarkan status live
            if ($request->has('is_live')) {
                $query->where('is_live', filter_var($request->is_live, FILTER_VALIDATE_BOOLEAN));
            } else {
                // Default hanya tampilkan toko yang live
                $query->where('is_live', true);
            }

            // Pencarian berdasarkan nama
            if ($request->has('search') && $request->search) {
                $searchTerm = '%' . $request->search . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', $searchTerm)
                      ->orWhere('description', 'LIKE', $searchTerm)
                      ->orWhere('category', 'LIKE', $searchTerm);
                });
            }

            // Jika ada koordinat, hitung jarak dan sort berdasarkan jarak
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

                // Jika sort_by adalah distance, gunakan distance untuk sorting
                if ($request->get('sort_by') === 'distance') {
                    $sortOrder = $request->get('sort_order', 'asc');
                    $query->orderBy('distance', $sortOrder);
                } else if (!$request->has('sort_by')) {
                    // Default sort by distance jika ada koordinat
                    $query->orderBy('distance', 'asc');
                }
            }

            // Sorting
            if ($request->has('sort_by') && $request->sort_by !== 'distance') {
                $sortOrder = $request->get('sort_order', 'asc');
                $query->orderBy($request->sort_by, $sortOrder);
            } else if (!$request->has('latitude') || !$request->has('longitude')) {
                // Default sorting jika tidak ada koordinat
                $query->orderBy('created_at', 'desc');
            }

            // Include menus jika diminta
            if ($request->boolean('with_menus')) {
                $query->with(['menus' => function($query) {
                    // Hapus kondisi is_available karena kolom tidak ada
                    $query->orderBy('name', 'asc');
                }]);
            }

            // Pagination
            $perPage = $request->get('per_page', 20);
            $shops = $query->paginate($perPage);

            // Transform data
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

                // Tambahkan distance jika ada
                if (isset($shop->distance)) {
                    $data['distance_km'] = round($shop->distance, 2);
                    $data['distance_m'] = round($shop->distance * 1000);
                }

                // Tambahkan menus jika diminta
                if ($request->boolean('with_menus') && $shop->relationLoaded('menus')) {
                    $data['menus'] = $shop->menus->map(function ($menu) {
                        return [
                            'id' => $menu->id,
                            'name' => $menu->name,
                            'description' => $menu->description,
                            'price' => $menu->price,
                            'image' => $menu->image ? asset('storage/' . $menu->image) : null,
                            // Hapus is_available karena kolom tidak ada
                        ];
                    });
                }

                // Tambahkan total menu jika tidak load semua menu
                if (!$request->boolean('with_menus')) {
                    // Hapus kondisi is_available
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
            // Mengambil semua kategori unik dari toko yang live
            $categories = Shop::where('is_live', true)
                ->distinct()
                ->pluck('category')
                ->filter() // Hapus nilai null
                ->values()
                ->toArray();

            // Jika ingin kategori default jika tidak ada data
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
            
            // Query untuk distribusi kategori
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
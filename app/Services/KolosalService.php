<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KolosalService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('KOLOSAL_API_KEY');
        $this->baseUrl = env('KOLOSAL_BASE_URL', 'https://api.kolosal.ai/v1');
    }

    private function getAddressFromCoordinates($lat, $lon)
    {
        try {
            // User-Agent wajib ada untuk kebijakan OpenStreetMap
            $response = Http::withHeaders([
                'User-Agent' => 'LokalakuApp/1.0 (Hackathon)'
            ])->timeout(5)->get("https://nominatim.openstreetmap.org/reverse", [
                'format' => 'json',
                'lat' => trim($lat),
                'lon' => trim($lon),
                'zoom' => 18,
                'addressdetails' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Ambil landmark atau nama jalan
                $road = $data['address']['road'] ?? '';
                $suburb = $data['address']['suburb'] ?? $data['address']['village'] ?? '';
                $city = $data['address']['city'] ?? $data['address']['town'] ?? '';
                $landmark = $data['address']['amenity'] ?? $data['address']['building'] ?? '';

                $addressParts = array_filter([$landmark, $road, $suburb, $city]);
                return implode(", ", $addressParts);
            }
        } catch (\Exception $e) {
            Log::warning("Geocoding failed: " . $e->getMessage());
        }

        return "Koordinat $lat, $lon";
    }

    public function getRecommendation($shopCategory, $currentLocation)
    {
        // 1. FIX TIMEZONE: Paksa ke WIB (Asia/Jakarta)
        // Agar tidak dianggap siang saat malam hari
        $timeObj = now()->setTimezone('Asia/Jakarta');
        $time = $timeObj->format('H:i');
        $date = $timeObj->format('l, d F Y');

        // Tentukan waktu deskriptif (Pagi/Siang/Sore/Malam)
        $hour = $timeObj->hour;
        if ($hour >= 5 && $hour < 11) $period = "Pagi";
        elseif ($hour >= 11 && $hour < 15) $period = "Siang";
        elseif ($hour >= 15 && $hour < 18) $period = "Sore";
        else $period = "Malam";

        // 2. FIX LOCATION: Ubah angka jadi alamat
        $coords = explode(',', $currentLocation);
        $lat = $coords[0] ?? 0;
        $lon = $coords[1] ?? 0;

        // Panggil helper geocoding
        $realAddress = $this->getAddressFromCoordinates($lat, $lon);

        // 3. PROMPT ENGINEERING YANG LEBIH KONTEKSTUAL
        $prompt = "Kamu adalah konsultan strategi lapangan untuk pedagang keliling ($shopCategory). \n" .
            "Saat ini adalah hari $date, Pukul $time ($period). \n" .
            "Posisi pedagang saat ini terdeteksi di: **$realAddress**. \n\n" .

            "TUGAS: Analisa area $realAddress tersebut. \n" .
            "Berikan 1 saran lokasi spesifik (nama tempat umum/gedung/taman) DALAM RADIUS 500 METER " .
            "dari alamat tersebut yang potensial ramai pembeli pada jam $time $period ini. \n\n" .

            "Contoh logika: Jika malam, cari taman pasar malam/alun-alun. Jika siang, cari sekolah/kantor. \n" .
            "JANGAN menyarankan tempat yang jauh (beda kecamatan/kota). \n\n" .

            "Jawab JSON: { \"message\": \"Kalimat penyemangat + alasan singkat (Sebutkan 'Selamat $period')\", \"target_location\": \"Nama Tempat Spesifik\" }.";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model' => 'Claude Sonnet 4.5',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 500
                ]);

            Log::info('Kolosal Seller Insight', [
                'input_location' => $realAddress,
                'input_time' => "$time ($period)",
                'response' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['choices'][0]['message']['content'])) {
                    $content = $data['choices'][0]['message']['content'];

                    // Bersihkan format Markdown JSON (```json ... ```)
                    $content = str_replace(['```json', '```'], '', $content);

                    $decoded = json_decode($content, true);

                    if ($decoded && isset($decoded['message'])) {
                        return $decoded;
                    }

                    // Regex Fallback
                    if (preg_match('/\{.*?\}/s', $content, $matches)) {
                        return json_decode($matches[0], true);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Kolosal Seller Exception: ' . $e->getMessage());
        }

        // Fallback Manual jika API Error
        return [
            'message' => "Selamat $period! Coba keliling ke area perumahan atau tongkrongan terdekat.",
            'target_location' => "Area Perumahan/Taman"
        ];
    }

    public function getUserRecommendation($weather, $latitude, $longitude, $nearbyShops)
    {
        if (empty($nearbyShops)) {
            return [
                'recommendation' => "Jelajahi area sekitar",
                'reason' => "Belum ada pedagang terdekat saat ini",
                'shop_name' => null
            ];
        }

        $shopList = collect($nearbyShops)->map(function ($shop) {
            return "{$shop['name']} ({$shop['category']}) - {$shop['distance']}m";
        })->implode(', ');

        $prompt = "Kamu adalah asisten kuliner pintar. " .
            "Cuaca saat ini: {$weather['description']}, suhu {$weather['temperature']}°C. " .
            "Lokasi user di koordinat: {$latitude}, {$longitude}. " .
            "Pedagang keliling terdekat dalam radius 1km: {$shopList}. " .
            "Berikan 1 rekomendasi makanan/minuman yang cocok dengan cuaca ini dan tersedia dari pedagang terdekat. " .
            "Jawab dalam format JSON: { " .
            "\"recommendation\": \"Nama makanan/minuman\", " .
            "\"reason\": \"Alasan singkat kenapa cocok dengan cuaca\", " .
            "\"shop_name\": \"Nama pedagang yang recommended\" " .
            "}.";

        try {
            Log::info('Calling Kolosal AI for user recommendation');

            $response = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model' => 'Claude Sonnet 4.5', // Sesuai screenshot
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 500
                ]);

            Log::info('Kolosal User API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['choices'][0]['message']['content'])) {
                    $content = $data['choices'][0]['message']['content'];
                    Log::info('AI Response Content', ['content' => $content]);

                    $decoded = json_decode($content, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['recommendation'])) {
                        return $decoded;
                    }

                    if (preg_match('/\{[^}]*"recommendation"[^}]*"reason"[^}]*"shop_name"[^}]*\}/s', $content, $matches)) {
                        $decoded = json_decode($matches[0], true);
                        if ($decoded && isset($decoded['recommendation'])) {
                            return $decoded;
                        }
                    }

                    Log::warning('Could not parse AI response as JSON', ['content' => $content]);
                }
            } else {
                $errorData = $response->json();
                Log::error('Kolosal User API Error', [
                    'status' => $response->status(),
                    'error' => $errorData
                ]);

                // Check if it's actually a balance issue
                if (isset($errorData['error']) && $errorData['error'] === 'insufficient_balance') {
                    Log::critical('⚠️ Kolosal Balance Habis! Gunakan fallback.');
                }
            }
        } catch (\Exception $e) {
            Log::error('Kolosal User Exception: ' . $e->getMessage());
        }

        // Smart fallback based on weather
        return $this->getSmartFallback($weather, $nearbyShops);
    }

    /**
     * Generate smart fallback recommendation based on weather
     */
    private function getSmartFallback($weather, $nearbyShops)
    {
        $temp = $weather['temperature'];
        $description = strtolower($weather['description']);
        $shopName = $nearbyShops[0]['name'] ?? "Pedagang Terdekat";

        if ($temp > 30) {
            return [
                'recommendation' => "Es Teh/Es Jeruk",
                'reason' => "Cuaca panas ({$temp} derajat), minuman dingin sangat menyegarkan",
                'shop_name' => $shopName
            ];
        } elseif (strpos($description, 'hujan') !== false || strpos($description, 'rain') !== false) {
            return [
                'recommendation' => "Gorengan & Teh Hangat",
                'reason' => "Cuaca hujan, cocok dengan makanan hangat",
                'shop_name' => $shopName
            ];
        } elseif ($temp < 25) {
            return [
                'recommendation' => "Kopi/Teh Hangat",
                'reason' => "Cuaca sejuk ({$temp} derajat), minuman hangat pas untuk menghangatkan badan",
                'shop_name' => $shopName
            ];
        } else {
            return [
                'recommendation' => "Es Teh Manis",
                'reason' => "Cuaca nyaman untuk minuman segar",
                'shop_name' => $shopName
            ];
        }
    }

    public function analyzeMarketOpportunities($competitorList, $myCategory, $myLocation)
    {
        $prompt = "Kamu adalah Konsultan Bisnis UMKM Kaki Lima yang jenius. \n" .
            "Saya pedagang kategori: '$myCategory'. \n" .
            "Lokasi saya di koordinat: $myLocation. \n\n" .

            "DATA KOMPETITOR (Radius 1KM dari saya):\n" .
            "$competitorList \n\n" .

            "TUGASMU:\n" .
            "1. Analisa Saturation: Apa jenis jualan yang sudah terlalu banyak/jenuh di sini?\n" .
            "2. Analisa Opportunity: Apa jenis jualan yang BELUM ADA tapi berpotensi laku (potensial)?\n" .
            "3. Berikan saran strategi buat saya (apakah harus ganti menu, atau pertahankan tapi tambah variasi).\n\n" .

            "JAWAB DALAM JSON SAJA:\n" .
            "{ \n" .
            "  \"saturated\": \"Ringkasan apa yang kebanyakan\", \n" .
            "  \"opportunity\": \"Saran jualan yang belum ada tapi dicari orang\", \n" .
            "  \"strategy\": \"Saran spesifik buat saya\", \n" .
            "  \"score\": 80 (Skor potensi lokasi 0-100) \n" .
            "}";

        try {
            Log::info('Calling Kolosal AI for Market Analysis');

            $response = Http::timeout(60) // Kasih waktu agak lama karena mikir analitik
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model' => 'Claude Sonnet 4.5',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 600
                ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? null;

                // Parsing JSON dari response AI
                if ($content) {
                    // Cari pattern JSON pakai Regex (untuk jaga-jaga ada teks tambahan)
                    if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                        $jsonContent = $matches[0];
                        return json_decode($jsonContent, true);
                    }
                }
            }

            Log::error('Kolosal Analysis Error', ['body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Kolosal Analysis Exception: ' . $e->getMessage());
        }

        // Fallback jika AI Gagal
        return [
            "saturated" => "Tidak dapat menganalisa data saat ini",
            "opportunity" => "Cobalah berjualan minuman segar atau camilan ringan",
            "strategy" => "Fokus pada pelayanan yang ramah dan kebersihan",
            "score" => 50
        ];
    }
}

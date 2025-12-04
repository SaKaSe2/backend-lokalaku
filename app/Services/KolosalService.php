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

    public function getRecommendation($shopCategory, $currentLocation)
    {
        $time = now()->format('H:i');

        $prompt = "Kamu adalah konsultan untuk pedagang keliling ($shopCategory). " .
            "Sekarang jam $time. Lokasi pedagang di koordinat $currentLocation. " .
            "Berikan 1 saran lokasi spesifik (nama tempat umum seperti sekolah/kantor/taman) di sekitar situ yang sedang ramai potensial pembeli saat ini. " .
            "Jawab dalam format JSON: { \"message\": \"Saran singkat menyemangati\", \"target_location\": \"Nama Tempat\" }.";

        try {
            $response = Http::timeout(30)
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

            Log::info('Kolosal Seller API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['choices'][0]['message']['content'])) {
                    $content = $data['choices'][0]['message']['content'];
                    $decoded = json_decode($content, true);

                    if ($decoded && isset($decoded['message'])) {
                        return $decoded;
                    }

                    if (preg_match('/\{.*?\}/s', $content, $matches)) {
                        $parsed = json_decode($matches[0], true);
                        if ($parsed) return $parsed;
                    }
                }
            } else {
                Log::error('Kolosal Seller API Error', [
                    'status' => $response->status(),
                    'error' => $response->json()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Kolosal Seller Exception: ' . $e->getMessage());
        }

        return [
            'message' => "Cari keramaian di sekitar pusat kota, semangat!",
            'target_location' => "Pusat Kota"
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
}

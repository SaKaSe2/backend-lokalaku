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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'kolosal-model-v1',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object']
            ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'];
                return json_decode($content, true);
            }

            Log::error('Kolosal API Fail: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Kolosal AI Exception: ' . $e->getMessage());
        }

        return [
            'message' => "Cari keramaian di sekitar pusat kota, semangat!",
            'target_location' => "Pusat Kota"
        ];
    }
    // TAMBAHKAN METHOD BARU INI
    public function getUserRecommendation($weather, $latitude, $longitude, $nearbyShops)
    {
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'kolosal-model-v1',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'response_format' => ['type' => 'json_object']
            ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'];
                return json_decode($content, true);
            }

            Log::error('Kolosal API Fail: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Kolosal AI Exception: ' . $e->getMessage());
        }

        return [
            'recommendation' => "Es Teh Manis",
            'reason' => "Minuman segar cocok untuk cuaca panas",
            'shop_name' => $nearbyShops[0]['name'] ?? "Pedagang Terdekat"
        ];
    }
}

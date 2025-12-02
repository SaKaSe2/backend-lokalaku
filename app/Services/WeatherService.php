<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        // Gunakan OpenWeatherMap API (gratis)
        $this->apiKey = env('WEATHER_API_KEY');
        $this->baseUrl = 'https://api.openweathermap.org/data/2.5';
    }

    public function getCurrentWeather($latitude, $longitude)
    {
        try {
            $response = Http::get($this->baseUrl . '/weather', [
                'lat' => $latitude,
                'lon' => $longitude,
                'appid' => $this->apiKey,
                'units' => 'metric', // Celsius
                'lang' => 'id'
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'temperature' => round($data['main']['temp']),
                    'feels_like' => round($data['main']['feels_like']),
                    'description' => $data['weather'][0]['description'],
                    'main' => $data['weather'][0]['main'], // Clear, Rain, Clouds, etc
                    'humidity' => $data['main']['humidity'],
                    'wind_speed' => $data['wind']['speed']
                ];
            }

            Log::error('Weather API Fail: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Weather Exception: ' . $e->getMessage());
        }

        return null;
    }
}

<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;

class MapboxService
{
    public function reverse(float $lat, float $lng): array
    {
        $response = Http::get(
            "https://api.mapbox.com/geocoding/v5/mapbox.places/{$lng},{$lat}.json",
            [
                'access_token' => config('services.mapbox.token'),
                'language' => 'ru',
                'limit' => 1,
            ]
        )->json();

        $place = data_get($response, 'features.0');

        return [
            'full' => $place['place_name'] ?? null,
            'short' => $place['text'] ?? null,
        ];
    }
}

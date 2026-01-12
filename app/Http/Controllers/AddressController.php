<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\MapboxService;

class AddressController extends Controller
{
    public function addressReverse(Request $request, MapboxService $mapbox)
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        // округляем — увеличивает cache hit
        $lat = round($data['lat'], 5);
        $lng = round($data['lng'], 5);

        $address = Cache::remember(
            "geo:reverse:$lat:$lng",
            now()->addDays(7),
            fn () => $mapbox->reverse($lat, $lng)
        );

        return response()->json([
            'address' => $address['full'] ?? null,
            'short'   => $address['short'] ?? null,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user()->car);
    }

    /**
     * Create car for current user. If user already has car -> 409
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $carId = $user->car?->id;

        $data = $request->validate([
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cars', 'number')->ignore($carId),
            ],
        ]);

        $car = Car::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return response()->json($car, 201);
    }
}


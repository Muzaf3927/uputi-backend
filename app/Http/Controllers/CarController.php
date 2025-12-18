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


        $data = $request->validate([
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'number' => 'required|string|max:50|unique:cars,number',
        ]);

        $car = Car::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );


        return response()->json($car, 201);
    }

    /**
     * Update current user's car (or create if missing -> upsert behaviour optional)
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('cars', 'number')
                    ->ignore($user->car?->id),
            ],
        ]);

        if ($user->car) {
            $user->car->update($data);
            return response()->json($user->car);
        }

        $car = Car::create(array_merge($data, [
            'user_id' => $user->id,
        ]));

        return response()->json($car, 201);
    }

}


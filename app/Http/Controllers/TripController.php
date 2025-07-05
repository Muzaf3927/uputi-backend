<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TripController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'from_city' => 'required|string|max:255',
            'to_city' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required',
            'seats' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'carModel' => 'nullable|string',
            'carColor' => 'nullable|string',
            'numberCar' => 'nullable|string',
        ]);

        $trip = Trip::create([
            'user_id' => Auth::id(),
            'from_city' => $request->from_city,
            'to_city' => $request->to_city,
            'date' => $request->date,
            'time' => $request->time,
            'seats' => $request->seats,
            'price' => $request->price,
            'note' => $request->note,
            'carModel' => $request->carModel,
            'carColor' => $request->carColor,
            'numberCar' => $request->numberCar,
        ]);
        return response()->json([
            'message' => 'Поездка создана!',
            'trip' => $trip,
        ]);
    }

    public function myTrips()
    {
        $trips = Trip::where('user_id', Auth::id())->orderByDesc('date')->get();
        return response()->json([
            'trips' => $trips
        ]);

    }

    public function index()
    {
        $trips = Trip::with('driver')->where('status', 'active')->orderBy('date')->get();
        return response()->json([
            'trips' => $trips
        ]);
    }
}


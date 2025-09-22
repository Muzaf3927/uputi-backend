<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Booking;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Notification;
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
            'carModel' => 'required|string',
            'carColor' => 'required|string',
            'numberCar' => [
                'required',
                'regex:/^[0-9]{2}[A-Z]{1}[0-9]{3}[A-Z]{2}$/'
            ],
        ], [
            'numberCar.regex' => 'Car number must be in format 01A000AA (only digits and uppercase Latin letters).'
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
            'message' => 'Trip created!',
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

    public function filter(Request $request)
    {
        $request->validate([
            'from_city' => 'nullable|string|max:255',
            'to_city'   => 'nullable|string|max:255',
            'date'      => 'nullable|date',
            'time'      => 'nullable|date_format:H:i',
        ]);

        $trips = Trip::with('driver')
            ->where('status', 'active')
            ->when($request->from_city, function ($query) use ($request) {
                $query->where('from_city', $request->from_city);
            })
            ->when($request->to_city, function ($query) use ($request) {
                $query->where('to_city', $request->to_city);
            })
            ->when($request->date, function ($query) use ($request) {
                $query->where('date', $request->date);
            })
            ->when($request->time, function ($query) use ($request) {
                $query->where('time', '>=', $request->time);
            })
            ->orderBy('date')
            ->orderBy('time')
            ->paginate(10) // 👈 пагинация
            ->through(function ($trip) {
                $trip->available_seats = $trip->available_seats;
                $trip->booked_seats = $trip->booked_seats;
                return $trip;
            });

        return response()->json($trips);
    }

    public function index()
    {
        $trips = Trip::with('driver')
            ->where('status', 'active')
            ->orderBy('date')
            ->get()
            ->map(function ($trip) {
                $trip->available_seats = $trip->available_seats;
                $trip->booked_seats = $trip->booked_seats;
                return $trip;
            });

        return response()->json([
            'trips' => $trips
        ]);
    }

    public function destroy(Trip $trip)
    {
        if ($trip->user_id !== Auth::id()) {
            return response()->json(['message' => 'Insufficient permissions to delete this trip'], 403);
        }

        $trip->delete();

        return response()->json(['message' => 'Trip deleted successfully']);
    }

    public function update(Request $request, Trip $trip)
    {
        if ($trip->user_id !== Auth::id()) {
            return response()->json(['message' => 'Insufficient permissions to update this trip'], 403);
        }

        $request->validate([
            'from_city' => 'required|string|max:255',
            'to_city' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required',
            'seats' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'carModel' => 'required|string',
            'carColor' => 'required|string',
            'numberCar' => [
                'required',
                'regex:/^[0-9]{2}[A-Z]{1}[0-9]{3}[A-Z]{2}$/'
            ],
        ], [
            'numberCar.regex' => 'Car number must be in format 01A000AA (only digits and uppercase Latin letters).'
        ]);

        $trip->update($request->only([
            'from_city', 'to_city', 'date', 'time', 'seats', 'price', 'note',
            'carModel', 'carColor', 'numberCar',
        ]));

        return response()->json([
            'message' => 'Trip updated!',
            'trip' => $trip,
        ]);
    }

    public function complete(Trip $trip)
    {
        if ($trip->status !== 'active') {
            return response()->json(['message' => 'This trip is already completed or cancelled.'], 400);
        }

        $trip->status = 'completed';
        $trip->save();

        // Нотификации только если реально были пассажиры
        if ($trip->bookings()->exists()) {
            foreach ($trip->bookings as $booking) {
                Notification::create([
                    'user_id'   => $booking->user_id,      // кому уведомление
                    'sender_id' => $trip->user_id,        // кто отправил (водитель)
                    'type'      => 'trip_completed',
                    'message'   => "Trip {$trip->from_city} → {$trip->to_city} completed.",
                    'data'      => json_encode([
                        'trip_id' => $trip->id,
                    ]),
                ]);
            }
        }

        return response()->json([
            'message' => 'Trip completed!',
            'trip' => $trip
        ]);
    }
}


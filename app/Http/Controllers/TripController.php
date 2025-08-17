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
            return response()->json(['message' => 'Недостаточно прав для удаления этой поездки'], 403);
        }

        $trip->delete();

        return response()->json(['message' => 'Поездка успешно удалена']);
    }

    public function update(Request $request, Trip $trip)
    {
        if ($trip->user_id !== Auth::id()) {
            return response()->json(['message' => 'Недостаточно прав для обновления этой поездки'], 403);
        }

        $request->validate([
            'from_city' => 'sometimes|string|max:255',
            'to_city' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'time' => 'sometimes',
            'seats' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'note' => 'nullable|string',
            'carModel' => 'nullable|string',
            'carColor' => 'nullable|string',
            'numberCar' => 'nullable|string',
        ]);

        $trip->update($request->only([
            'from_city', 'to_city', 'date', 'time', 'seats', 'price', 'note',
            'carModel', 'carColor', 'numberCar',
        ]));

        return response()->json([
            'message' => 'Поездка обновлена!',
            'trip' => $trip,
        ]);
    }

    public function complete(Trip $trip)
    {
        if ($trip->user_id !== Auth::id()) {
            return response()->json(['message' => 'Недостаточно прав для завершения этой поездки'], 403);
        }

        if ($trip->status !== 'active') {
            return response()->json(['message' => 'Поездка уже завершена или отменена'], 422);
        }

        // Получаем все подтвержденные бронирования для этой поездки
        $confirmedBookings = $trip->bookings()->where('status', 'confirmed')->get();

        if ($confirmedBookings->isEmpty()) {
            return response()->json(['message' => 'Нет подтвержденных пассажиров для завершения поездки'], 422);
        }

        // Списываем комиссию с водителя (1000 сум)
        $driverWallet = $trip->driver->wallet;
        if (!$driverWallet) {
            $driverWallet = Wallet::create(['user_id' => $trip->user_id]);
        }

        if ($driverWallet->balance < 1000) {
            return response()->json(['message' => 'Недостаточно средств на балансе водителя для завершения поездки'], 422);
        }

        $driverWallet->balance -= 1000;
        $driverWallet->save();

        Transaction::create([
            'wallet_id' => $driverWallet->id,
            'type' => 'trip_completion_fee',
            'amount' => 1000,
            'description' => "Комиссия за завершение поездки {$trip->from_city} → {$trip->to_city}"
        ]);

        // Списываем комиссию с каждого пассажира (50 сум)
        foreach ($confirmedBookings as $booking) {
            $passengerWallet = $booking->user->wallet;
            if (!$passengerWallet) {
                $passengerWallet = Wallet::create(['user_id' => $booking->user_id]);
            }

            if ($passengerWallet->balance >= 50) {
                $passengerWallet->balance -= 50;
                $passengerWallet->save();

                Transaction::create([
                    'wallet_id' => $passengerWallet->id,
                    'type' => 'trip_completion_fee',
                    'amount' => 50,
                    'description' => "Комиссия за завершение поездки {$trip->from_city} → {$trip->to_city}"
                ]);

                // Создаем уведомление для пассажира
                Notification::create([
                    'user_id' => $booking->user_id,
                    'sender_id' => $trip->user_id,
                    'type' => 'trip_completed',
                    'message' => "Поездка {$trip->from_city} → {$trip->to_city} завершена. Списана комиссия 50 сум.",
                    'data' => json_encode([
                        'trip_id' => $trip->id,
                        'fee_amount' => 50
                    ]),
                ]);
            }
        }

        // Завершаем поездку
        $trip->status = 'completed';
        $trip->save();

        // Создаем уведомление для водителя
        Notification::create([
            'user_id' => $trip->user_id,
            'sender_id' => $trip->user_id,
            'type' => 'trip_completed',
            'message' => "Поездка {$trip->from_city} → {$trip->to_city} завершена. Списана комиссия 1000 сум.",
            'data' => json_encode([
                'trip_id' => $trip->id,
                'fee_amount' => 1000,
                'passengers_count' => $confirmedBookings->count()
            ]),
        ]);

        return response()->json([
            'message' => 'Поездка успешно завершена',
            'trip' => $trip,
            'driver_fee' => 1000,
            'passenger_fee' => 50,
            'passengers_count' => $confirmedBookings->count(),
            'total_passenger_fees' => $confirmedBookings->count() * 50
        ]);
    }
}


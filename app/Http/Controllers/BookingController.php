<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class BookingController extends Controller
{
    public function store(Request $request, Trip $trip)
    {
        $request->validate([
            'seats' => 'required|integer|min:1|max:' . $trip->seats,
        ]);

        $existing = Booking::where('trip_id', $trip->id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$existing || $existing->status === 'cancelled' ) {
            $booking = Booking::updateOrCreate(
                ['trip_id' => $trip->id, 'user_id' => Auth::id()],
                ['seats' => $request->seats, 'status' => 'pending']
            );

            return response()->json(['message' => 'Заявка отправлена', 'booking' => $booking,], 201);

        } elseif ($existing->status === 'confirmed') {
            return response()->json(['message' => 'Вы уже забронировали место'], 200);
        } elseif ($existing->status === 'declined') {
            return response()->json(['message' => 'Водитель не одобрил'], 403);
        }

        return response()->json(['message' => 'Ваша заявка в ожидание'], 403);
    }

    public function update(Request $request, Booking $booking)
    {
        $user = Auth::user();
        $trip = $booking->trip;

        if ($user->id !== $trip->user_id) {
            return response()->json(['message' => 'Нет доступа'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,declined,cancelled',
                            //в ожидании, подтверждено, отклонено, отменено
        ]);

        $booking->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Статус обновлен',
            'status' => $booking->status
        ], 201);
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'Нет доступа'], 403);
        }

        if (in_array($booking->status, ['confirmed', 'pending', 'declined', 'cancelled'])) {
            $booking->status = 'cancelled';
            $booking->save();

            return response()->json(['message' => 'Заявка отменена']);
        }

        return response()->json(['message' => 'Нельзя отменить заявку в этом статусе'], 422);
    }

    public function myBookings()
    {
        $bookings = Booking::with('trip')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['bookings' => $bookings]);
    }

    public function tripBookings(Trip $trip)
    {
        if ($trip->user_id !== Auth::id()) {
            return response()->json(['message' => 'Нет доступа'], 403);
        }

        $bookings = $trip->bookings()->with('user')->get();

        return response()->json(['bookings' => $bookings]);
    }
}


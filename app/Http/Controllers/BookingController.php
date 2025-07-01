<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function store(Request $request, Trip $trip)
    {
        $request->validate([
            'seats' => 'required|integer|min:1|max:' . $trip->seats,
        ]);

        // Проверим: уже есть заявка от этого юзера?
        $existing = Booking::where('trip_id', $trip->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            return back()->with('error', 'Вы уже отправили заявку на эту поездку.');
        }

        Booking::create([
            'trip_id' => $trip->id,
            'user_id' => Auth::id(),
            'seats' => $request->seats,
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Заявка отправлена');
    }

    public function update(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking->trip); // Только водитель может подтверждать

        $request->validate([
            'status' => 'required|in:confirmed,declined',
        ]);

        $booking->update(['status' => $request->status]);

        return back()->with('success', 'Статус обновлен');
    }
}


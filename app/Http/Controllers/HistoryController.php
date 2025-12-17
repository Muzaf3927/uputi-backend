<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function passengerHistory(Request $request)
    {
        $user = $request->user();

        $myTrips = Trip::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('role', 'passenger')
            ->with('bookings.user.car')
            ->paginate(5);

        $myBookings = Booking::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('role', 'passenger')
            ->with('trip.user')
            ->paginate(5);

        return response()->json([
            'trips' => $myTrips,
            'bookings' => $myBookings,
        ]);
    }

    public function driverHistory(Request $request)
    {
        $user = $request->user();

        $myTrips = Trip::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('role', 'driver')
            ->with('bookings.user')
            ->paginate(5);

        $myBookings = Booking::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('role', 'driver')
            ->with('trip.user')
            ->paginate(5);

        return response()->json([
            'trips' => $myTrips,
            'bookings' => $myBookings,
        ]);

    }
}

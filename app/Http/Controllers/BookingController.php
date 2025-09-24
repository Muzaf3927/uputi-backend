<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class BookingController extends Controller
{
    public function store(Request $request, Trip $trip)
    {
        $request->validate([
            'seats' => 'required|integer|min:1',
            'offered_price' => 'nullable|integer|min:0',
            'comment' => 'nullable|string|max:500',
        ]);

        // Проверяем, что запрашиваемое количество мест не превышает доступное
        if ($request->seats > $trip->seats) {
            return response()->json([
                'message' => 'Requested number of seats exceeds available',
                'available_seats' => $trip->seats,
                'requested_seats' => $request->seats
            ], 422);
        }

        $existing = Booking::where('trip_id', $trip->id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$existing || $existing->status === 'cancelled') {
            $booking = Booking::updateOrCreate(
                ['trip_id' => $trip->id, 'user_id' => Auth::id()],
                [
                    'seats' => $request->seats,
                    'status' => 'pending',
                    'offered_price' => $request->offered_price,
                    'comment' => $request->comment,
                ]
            );

            $message = Auth::user()->name . " sent a booking request for trip {$trip->from_city} → {$trip->to_city}";

            if ($request->offered_price) {
                $message = Auth::user()->name . " offers price {$request->offered_price} sum for trip {$trip->from_city} → {$trip->to_city}";
            }

            Notification::create([
                'user_id' => $trip->user_id,
                'sender_id' => Auth::id(),
                'type' => 'new_booking',
                'message' => $message,
                'data' => json_encode([
                    'trip_id'       => $trip->id,
                    'booking_id'    => $booking->id,
                    'passenger_id'  => Auth::id(),
                    'offered_price' => $booking->offered_price,
                    'comment'       => $booking->comment,
                ]),
            ]);

            return response()->json([
                'message' => 'Booking request sent',
                'booking' => $booking,
            ], 201);

        } elseif ($existing->status === 'confirmed') {
            return response()->json(['message' => 'You have already booked a seat'], 200);
        } elseif ($existing->status === 'declined') {
            return response()->json(['message' => 'Driver did not approve'], 403);
        }

        return response()->json(['message' => 'Your request is pending'], 403);
    }

    public function update(Request $request, Booking $booking)
    {
        $user = Auth::user();
        $trip = $booking->trip;

        if ($user->id !== $trip->user_id) {
            return response()->json(['message' => 'No access'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,declined,cancelled',
        ]);

        $oldStatus = $booking->status;
        $newStatus = $validated['status'];

        // Обновляем статус бронирования
        $booking->update(['status' => $newStatus]);

        // Обновляем количество свободных мест в поездке
        if ($oldStatus !== $newStatus) {
            if ($newStatus === 'confirmed' && $oldStatus !== 'confirmed') {
                // Подтверждаем бронирование - уменьшаем количество мест
                if ($trip->seats >= $booking->seats) {
                    $trip->seats -= $booking->seats;
                    $trip->save();

                    // Создаем приветственное сообщение от водителя к пассажиру
                    $welcomeMessage = ChatMessage::create([
                        'trip_id' => $trip->id,
                        'sender_id' => $trip->user_id, // водитель
                        'receiver_id' => $booking->user_id, // пассажир
                        'message' => "Hello! I confirmed your booking request for trip {$trip->from_city} → {$trip->to_city}. " .
                            "You offered: {$booking->offered_price} sum. " .
                            ($booking->comment ? "Comment: {$booking->comment}" : ""),
                    ]);

                    // Создаем уведомление для пассажира
                    Notification::create([
                        'user_id' => $booking->user_id,
                        'sender_id' => $trip->user_id,
                        'type' => 'booking_confirmed',
                        'message' => "Your booking request for trip {$trip->from_city} → {$trip->to_city} confirmed!",
                        'data' => json_encode([
                            'trip_id' => $trip->id,
                            'booking_id' => $booking->id,
                            'chat_message_id' => $welcomeMessage->id
                        ]),
                    ]);

                } else {
                    return response()->json([
                        'message' => 'Not enough available seats for confirmation',
                        'available_seats' => $trip->seats,
                        'requested_seats' => $booking->seats
                    ], 422);
                }
            } elseif ($oldStatus === 'confirmed' && $newStatus !== 'confirmed') {
                // Отменяем подтвержденное бронирование - возвращаем места
                $trip->seats += $booking->seats;
                $trip->save();

                // Создаем уведомление об отмене
                Notification::create([
                    'user_id' => $booking->user_id,
                    'sender_id' => $trip->user_id,
                    'type' => 'booking_cancelled',
                    'message' => "Your booking request for trip {$trip->from_city} → {$trip->to_city} was cancelled by the driver.",
                    'data' => json_encode([
                        'trip_id' => $trip->id,
                        'booking_id' => $booking->id
                    ]),
                ]);
            }
        }

        return response()->json([
            'message' => 'Status updated',
            'status' => $booking->status,
            'trip_seats_remaining' => $trip->seats,
            'passenger_offer' => [
                'offered_price' => $booking->offered_price,
                'comment' => $booking->comment
            ],
            'trip_price' => $trip->price // price originally set by driver
        ], 201);
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'No access'], 403);
        }

        if (in_array($booking->status, ['confirmed', 'pending', 'declined'])) {
            $oldStatus = $booking->status;
            $trip = $booking->trip;
            $passengerName = Auth::user()->name;

            // Если отменяем подтвержденное бронирование, возвращаем места
            if ($oldStatus === 'confirmed') {
                $trip->seats += $booking->seats;
                $trip->save();

                // Отправляем уведомление водителю
                Notification::create([
                    'user_id' => $trip->user_id, // водитель
                    'sender_id' => Auth::id(), // пассажир
                    'type' => 'booking_cancelled_by_passenger',
                    'message' => "{$passengerName} отменил заявку на поездку {$trip->from_city} → {$trip->to_city}",
                    'data' => json_encode([
                        'trip_id' => $trip->id,
                        'booking_id' => $booking->id,
                        'passenger_name' => $passengerName,
                        'old_status' => $oldStatus
                    ]),
                ]);
            }

            $booking->delete();

            return response()->json([
                'message' => 'Заявка успешно отменена',
                'trip_seats_remaining' => $trip->seats,
                'deleted' => true
            ]);
        }

        return response()->json(['message' => 'Cannot cancel booking in this status'], 422);
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
            return response()->json(['message' => 'No access'], 403);
        }

        $bookings = $trip->bookings()->with('user')->get();

        return response()->json(['bookings' => $bookings]);
    }

    // List my own pending booking requests (I sent, driver not confirmed yet)
    public function myPendingBookings()
    {
        $bookings = Booking::with('trip')
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['bookings' => $bookings]);
    }

    // List pending requests to my trips (others sent to my trips, I haven't confirmed yet)
    public function pendingBookingsToMyTrips()
    {
        $bookings = Booking::with(['trip', 'user'])
            ->where('status', 'pending')
            ->whereHas('trip', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['bookings' => $bookings]);
    }
}


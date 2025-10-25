<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    // Sozdat bronirovanie (passazhir otpravlyaet zapros)
    public function store(Request $request, Trip $trip)
    {
        $request->validate([
            'seats' => 'required|integer|min:1',
            'offered_price' => 'nullable|integer|min:0',
            'comment' => 'nullable|string|max:500',
        ]);

        // proveryaem, chto zaprashivaemoe kolichestvo mest ne prevyshaet dostupnoe
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

            $message = Auth::user()->name . " {$trip->from_city} → {$trip->to_city} safariga so'rov junatdi ";

            if ($request->offered_price) {
                $message = Auth::user()->name . " {$trip->from_city} → {$trip->to_city} safariga {$request->offered_price} sum taklif qilyapti";
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

    // Obnovit status bronirovaniya (voditel prinyal/otklonil)
    public function update(Request $request, Booking $booking)
    {
        $user = Auth::user();
        $trip = $booking->trip;

        if ($user->id !== $trip->user_id) {
            return response()->json(['message' => 'No access'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:confirmed,declined,cancelled',
        ]);

        $oldStatus = $booking->status;
        $newStatus = $validated['status'];

        // obnovlyaem status bronirovaniya
        $booking->update(['status' => $newStatus]);

        // obnovlyaem kolichestvo svobodnyh mest v poezdke
        if ($oldStatus !== $newStatus) {
            if ($newStatus === 'confirmed' && $oldStatus !== 'confirmed') {
                // podtverzhdaem bronirovanie - umenshaem kolichestvo mest
                if ($trip->seats >= $booking->seats) {
                    $trip->seats -= $booking->seats;
                    $trip->save();

                    // sozdaem privetstvennoe soobshenie v chate
                    $welcomeMessage = ChatMessage::create([
                        'trip_id' => $trip->id,
                        'sender_id' => $trip->user_id, // voditel
                        'receiver_id' => $booking->user_id, // passazhir
                        'message' => "Salom, man sizning {$trip->from_city} → {$trip->to_city}, {$trip->data}, {$trip->time} so'rovingizni qabul qildim"
                    ]);

                    // sozdanie uvedomleniya dlya passazhira
                    Notification::create([
                        'user_id' => $booking->user_id,
                        'sender_id' => $trip->user_id,
                        'type' => 'booking_confirmed',
                        'message' => "So'rovingiz {$trip->from_city} → {$trip->to_city}, {$trip->data}, {$trip->time} qabul qilindi!",
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
                // otmenyaem podtverzhdennoe bronirovanie - vozvrashaem mesta
                $trip->seats += $booking->seats;
                $trip->save();

                // sozdanie uvedomleniya ob otmene
                Notification::create([
                    'user_id' => $booking->user_id,
                    'sender_id' => $trip->user_id,
                    'type' => 'booking_cancelled',
                    'message' => "So'rovingiz {$trip->from_city} → {$trip->to_city}, {$trip->data}, {$trip->time} haydovchi tomonidan rad etildi",
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
            'trip_price' => $trip->price // tsena, kotoruyu ukazal voditel
        ], 201);
    }

    // Otmenit moe bronirovanie (passazhir otmenyaet)
    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'No access'], 403);
        }

        if (!in_array($booking->status, ['confirmed', 'pending'])) {
            return response()->json(['message' => 'Cannot cancel booking in this status'], 422);
        }

        $trip = $booking->trip;
        $oldStatus = $booking->status;
        $passengerName = Auth::user()->name;

        if ($oldStatus === 'confirmed') {
            $trip->seats += $booking->seats;
            $trip->save();
        }

        // vsegda uvedomlyaem voditelya
        Notification::create([
            'user_id' => $trip->user_id, // voditel
            'sender_id' => Auth::id(),
            'type' => 'booking_cancelled_by_passenger',
            'message' => "{$passengerName} $trip->from_city → {$trip->to_city}, {$trip->data}, {$trip->time} bo'yicha surovini bekor qildi",
            'data' => json_encode([
                'trip_id' => $trip->id,
                'booking_id' => $booking->id,
                'old_status' => $oldStatus
            ]),
        ]);

        // Удаляем бронь вместо смены статуса
        $booking->delete();

        return response()->json([
            'message' => 'Zayavka uspeshno otmenena i udalena',
            'trip_seats_remaining' => $trip->seats,
        ]);
    }

    // 1. Moi bronirovaniya (gde ya passazhir, status confirmed)
    public function myConfirmedBookings()
    {
        $bookings = Booking::with(['trip.driver'])
            ->where('user_id', Auth::id())
            ->where('status', 'confirmed')
            ->orderByDesc('created_at')
            ->get();

        // Отмечаем все мои confirmed бронирования как прочитанные
        Booking::where('user_id', Auth::id())
            ->where('status', 'confirmed')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['bookings' => $bookings]);
    }

    // 2. Moi zaprosy (gde ya passazhir, status pending)
    public function myPendingBookings()
    {
        $bookings = Booking::with('trip')
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        // Отмечаем все мои pending запросы как прочитанные
        Booking::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['bookings' => $bookings]);
    }

    // 3. Zayavki na moi poezdki (gde ya voditel, status confirmed)
    public function confirmedBookingsToMyTrips()
    {
        $bookings = Booking::with(['trip', 'user'])
            ->where('status', 'confirmed')
            ->whereHas('trip', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderByDesc('created_at')
            ->get();

        // Отмечаем все confirmed заявки на мои поездки как прочитанные
        Booking::where('status', 'confirmed')
            ->whereHas('trip', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['bookings' => $bookings]);
    }

    // 4. Zayavki na moi poezdki (gde ya voditel, status pending)
    public function pendingBookingsToMyTrips()
    {
        $bookings = Booking::with(['trip', 'user'])
            ->where('status', 'pending')
            ->whereHas('trip', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderByDesc('created_at')
            ->get();

        // Отмечаем все pending заявки на мои поездки как прочитанные
        Booking::where('status', 'pending')
            ->whereHas('trip', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['bookings' => $bookings]);
    }

    // Получить количество непрочитанных заявок для каждого раздела
    public function unreadCount()
    {
        $myConfirmedUnread = Booking::where('user_id', Auth::id())
            ->where('status', 'confirmed')
            ->where('is_read', false)
            ->count();

        $myPendingUnread = Booking::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->where('is_read', false)
            ->count();

        $toMyTripsConfirmedUnread = Booking::where('status', 'confirmed')
            ->whereHas('trip', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->where('is_read', false)
            ->count();

        $toMyTripsPendingUnread = Booking::where('status', 'pending')
            ->whereHas('trip', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->where('is_read', false)
            ->count();

        return response()->json([
            'my_confirmed_unread' => $myConfirmedUnread,
            'my_pending_unread' => $myPendingUnread,
            'to_my_trips_confirmed_unread' => $toMyTripsConfirmedUnread,
            'to_my_trips_pending_unread' => $toMyTripsPendingUnread,
        ]);
    }

    public function show(Trip $trip)
    {
        // Berem vse bookingi bez ogranicheniy po statusu
        $bookings = $trip->bookings()
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['bookings' => $bookings]);

    }

}

<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Rating;
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

    public function myTrips(Request $request)
    {
        // Кол-во записей на страницу (по умолчанию 5)
        $perPage = $request->get('per_page', 5);

        $trips = Trip::where('user_id', Auth::id())
            ->orderByDesc('date')
            ->paginate($perPage);

        // Добавляем пассажиров к каждой поездке
        $trips->getCollection()->transform(function ($trip) {
            // confirmed пассажиры
            $confirmed = $trip->bookings()
                ->where('status', 'confirmed')
                ->with('user:id,name')
                ->get()
                ->pluck('user');

            // pending пассажиры
            $pending = $trip->bookings()
                ->where('status', 'pending')
                ->with('user:id,name')
                ->get()
                ->pluck('user');

            $trip->confirmed_passengers = $confirmed;
            $trip->pending_passengers = $pending;

            return $trip;
        });

        return response()->json($trips);
    }

    public function index(Request $request)
    {
        $request->validate([
            'from_city' => 'nullable|string|max:255',
            'to_city'   => 'nullable|string|max:255',
            'date'      => 'nullable|date',
            'time'      => 'nullable|date_format:H:i',
        ]);

        $perPage = $request->get('per_page', 10);

        $trips = Trip::with(['driver', 'bookings' => function ($query) {
            $query->where('user_id', Auth::id());
        }])
            ->where('status', 'active')
            ->when($request->from_city, fn($query) => $query->where('from_city', $request->from_city))
            ->when($request->to_city, fn($query) => $query->where('to_city', $request->to_city))
            ->when($request->date, fn($query) => $query->where('date', $request->date))
            ->when($request->time, fn($query) => $query->where('time', '>=', $request->time))
            ->orderBy('date')
            ->orderBy('time')
            ->paginate($perPage)
            ->through(function ($trip) {
                $trip->available_seats = $trip->available_seats;
                $trip->booked_seats = $trip->booked_seats;

                $userBooking = $trip->bookings->first();
                $trip->my_booking = $userBooking ? [
                    'id' => $userBooking->id,
                    'status' => $userBooking->status,
                    'seats' => $userBooking->seats,
                    'offered_price' => $userBooking->offered_price,
                    'comment' => $userBooking->comment,
                    'can_cancel' => in_array($userBooking->status, ['pending', 'confirmed']),
                    'status_message' => $this->getBookingStatusMessage($userBooking->status)
                ] : null;

                unset($trip->bookings);

                return $trip;
            });

        return response()->json($trips);
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
                    'message'   => "Poezdka {$trip->from_city} → {$trip->to_city} zavershena.",
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

        public function myCompletedTrips()
        {
            $trips = Trip::where('user_id', Auth::id())
                ->where('status', 'completed')
                ->with(['bookings' => function ($q) {
                    $q->where('status', 'confirmed')->with('passenger:id,name');
                }])
                ->orderByDesc('date')
                ->orderByDesc('time')
                ->paginate(5); // <<< пагинация

            // Преобразуем каждую страницу
            $trips->getCollection()->transform(function ($trip) {
                $participants = $trip->bookings->map(function ($b) use ($trip) {
                    $alreadyRated = Rating::where('from_user_id', Auth::id())
                        ->where('to_user_id', $b->user_id)
                        ->where('trip_id', $trip->id)
                        ->exists();

                    return [
                        'user' => [
                            'id' => $b->passenger?->id,
                            'name' => $b->passenger?->name,
                        ],
                        'can_rate' => !$alreadyRated,
                    ];
                });

                return [
                    'id' => $trip->id,
                    'from_city' => $trip->from_city,
                    'to_city' => $trip->to_city,
                    'date' => $trip->date,
                    'time' => $trip->time,
                    'price' => $trip->price,
                    'participants' => $participants,
                    'role' => 'driver'
                ];
            });

            return response()->json($trips);
        }

        public function myCompletedTripsAsPassenger()
        {
            $trips = Trip::where('status', 'completed')
                ->whereHas('bookings', function ($q) {
                    $q->where('user_id', Auth::id())
                        ->where('status', 'confirmed');
                })
                ->with(['driver:id,name'])
                ->orderByDesc('date')
                ->orderByDesc('time')
                ->paginate(5); // <<< пагинация

            $trips->getCollection()->transform(function ($trip) {
                $alreadyRated = Rating::where('from_user_id', Auth::id())
                    ->where('to_user_id', $trip->user_id)
                    ->where('trip_id', $trip->id)
                    ->exists();

                return [
                    'id' => $trip->id,
                    'from_city' => $trip->from_city,
                    'to_city' => $trip->to_city,
                    'date' => $trip->date,
                    'time' => $trip->time,
                    'price' => $trip->price,
                    'driver' => [
                        'id' => $trip->driver?->id,
                        'name' => $trip->driver?->name,
                    ],
                    'can_rate' => !$alreadyRated,
                    'role' => 'passenger'
                ];
            });

            return response()->json($trips);
        }
    /**
     * Получить сообщение о статусе заявки
     */
    private function getBookingStatusMessage($status)
    {
        switch ($status) {
            case 'pending':
                return 'Vasha zayavka ojidet potverjdenie';
            case 'confirmed':
                return 'Vasha zayavka potverjdena';
            case 'declined':
                return 'Vasha zayavka otkloneno';
            case 'cancelled':
                return 'Vasha zayavka otmineno';
            default:
                return 'Neizvestniy status';
        }
    }
}


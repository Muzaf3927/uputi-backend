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

//    public function myTrips(Request $request)
//    {
//        // Кол-во записей на страницу (по умолчанию 5)
//        $perPage = $request->get('per_page', 5);
//
//        $trips = Trip::where('user_id', Auth::id())
//            ->orderByDesc('date')
//            ->paginate($perPage);
//
//        // Добавляем пассажиров к каждой поездке
//        $trips->getCollection()->transform(function ($trip) {
//            // confirmed пассажиры
//            $confirmed = $trip->bookings()
//                ->where('status', 'confirmed')
//                ->with('user:id,name,phone,rating')
//                ->get()
//                ->pluck('user');
//
//            // pending пассажиры
//            $pending = $trip->bookings()
//                ->where('status', 'pending')
//                ->with('user:id,name,phone,rating')
//                ->get()
//                ->pluck('user');
//
//            $trip->confirmed_passengers = $confirmed;
//            $trip->pending_passengers = $pending;
//
//            return $trip;
//        });
//
//        return response()->json($trips);
//    }
    public function myTrips(Request $request)
    {
        $perPage = $request->get('per_page', 5);

        $trips = Trip::where('user_id', Auth::id())
            ->orderByDesc('date')
            ->paginate($perPage);

        $trips->getCollection()->transform(function ($trip) {
            // confirmed
            $confirmedBookings = $trip->bookings()
                ->where('status', 'confirmed')
                ->with('user:id,name,phone,rating')
                ->get();

            $confirmedSeats = $confirmedBookings->sum('seats');

            // pending
            $pendingBookings = $trip->bookings()
                ->where('status', 'pending')
                ->with('user:id,name,phone,rating')
                ->get();

            $pendingSeats = $pendingBookings->sum('seats');

            // Добавляем в результат
            return [
                'id' => $trip->id,
                'from_city' => $trip->from_city,
                'to_city' => $trip->to_city,
                'date' => $trip->date,
                'time' => $trip->time,
                'price' => $trip->price,
                'status' => $trip->status,
                'carModel' => $trip->carModel,
                'carColor' => $trip->carColor,
                'numberCar' => $trip->numberCar,
                'seats_total' => $trip->seats,
                'confirmed_seats' => $confirmedSeats,
                'pending_seats' => $pendingSeats,
                'available_seats' => $trip->seats - $confirmedSeats,
                'confirmed_passengers' => $confirmedBookings->map(function ($b) {
                    return [
                        'id' => $b->user->id,
                        'name' => $b->user->name,
                        'phone' => $b->user->phone,
                        'rating' => $b->user->rating,
                        'seats' => $b->seats, // <-- сколько он забронировал
                    ];
                }),
                'pending_passengers' => $pendingBookings->map(function ($b) {
                    return [
                        'id' => $b->user->id,
                        'name' => $b->user->name,
                        'phone' => $b->user->phone,
                        'rating' => $b->user->rating,
                        'seats' => $b->seats, // <-- сколько он запросил
                    ];
                }),
            ];
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
            ->when($request->from_city, function($query) use ($request) {
                $variants = $this->generateSearchVariants($request->from_city);
                $query->where(function($q) use ($variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('from_city', 'LIKE', '%'.$v.'%');
                    }
                });
            })
            ->when($request->to_city, function($query) use ($request) {
                $variants = $this->generateSearchVariants($request->to_city);
                $query->where(function($q) use ($variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('to_city', 'LIKE', '%'.$v.'%');
                    }
                });
            })
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

    private function generateSearchVariants(string $input): array
    {
        $variants = [];

        $trimmed = trim($input);
        if ($trimmed === '') {
            return [$trimmed];
        }

        // Base forms
        $variants[] = $trimmed;
        $variants[] = mb_strtolower($trimmed);

        // Latin <-> Cyrillic (Uzbek/Russian common) transliteration
        $variants[] = $this->latinToCyr($trimmed);
        $variants[] = $this->latinToCyr(mb_strtolower($trimmed));
        $variants[] = $this->cyrToLatin($trimmed);
        $variants[] = $this->cyrToLatin(mb_strtolower($trimmed));

        // Broaden: treat x and h as interchangeable (bux ~ buh)
        $xHVariants = [];
        $xHVariants[] = str_replace('x', 'h', str_replace('X', 'H', $trimmed));
        $xHVariants[] = str_replace('h', 'x', str_replace('H', 'X', $trimmed));
        foreach ($xHVariants as $v) {
            $variants[] = $v;
            $variants[] = mb_strtolower($v);
            $variants[] = $this->latinToCyr($v);
            $variants[] = $this->cyrToLatin($v);
        }

        // Unique, non-empty
        $variants = array_values(array_filter(array_unique($variants), function($v) {
            return $v !== '' && $v !== null;
        }));

        return $variants;
    }

    private function latinToCyr(string $text): string
    {
        // Common Uzbek Latin -> Cyrillic mapping (rough; good enough for search)
        $map = [
            'Yo' => 'Ё', 'YO' => 'Ё', 'yo' => 'ё',
            'Ya' => 'Я', 'YA' => 'Я', 'ya' => 'я',
            'Yu' => 'Ю', 'YU' => 'Ю', 'yu' => 'ю',
            'Sh' => 'Ш', 'SH' => 'Ш', 'sh' => 'ш',
            'Ch' => 'Ч', 'CH' => 'Ч', 'ch' => 'ч',
            "G'" => 'Ғ', "g'" => 'ғ',
            "O'" => 'Ў', "o'" => 'ў',
            'Ng' => 'Нг', 'NG' => 'НГ', 'ng' => 'нг',
            'A' => 'А', 'a' => 'а',
            'B' => 'Б', 'b' => 'б',
            'V' => 'В', 'v' => 'в',
            'G' => 'Г', 'g' => 'г',
            'D' => 'Д', 'd' => 'д',
            'E' => 'Е', 'e' => 'е',
            'J' => 'Ж', 'j' => 'ж',
            'Z' => 'З', 'z' => 'з',
            'I' => 'И', 'i' => 'и',
            'Y' => 'Й', 'y' => 'й',
            'K' => 'К', 'k' => 'к',
            'L' => 'Л', 'l' => 'л',
            'M' => 'М', 'm' => 'м',
            'N' => 'Н', 'n' => 'н',
            'O' => 'О', 'o' => 'о',
            'P' => 'П', 'p' => 'п',
            'R' => 'Р', 'r' => 'р',
            'S' => 'С', 's' => 'с',
            'T' => 'Т', 't' => 'т',
            'U' => 'У', 'u' => 'у',
            // Treat x/h close for search reach
            'X' => 'Х', 'x' => 'х',
            'H' => 'Ҳ', 'h' => 'ҳ',
            'Q' => 'Қ', 'q' => 'қ',
            'F' => 'Ф', 'f' => 'ф',
            'C' => 'К', 'c' => 'к',
            'W' => 'В', 'w' => 'в',
        ];

        return strtr($text, $map);
    }

    private function cyrToLatin(string $text): string
    {
        $map = [
            'Ё' => 'Yo', 'ё' => 'yo',
            'Я' => 'Ya', 'я' => 'ya',
            'Ю' => 'Yu', 'ю' => 'yu',
            'Ш' => 'Sh', 'ш' => 'sh',
            'Ч' => 'Ch', 'ч' => 'ch',
            'Ғ' => "G'", 'ғ' => "g'",
            'Ў' => "O'", 'ў' => "o'",
            'А' => 'A', 'а' => 'a',
            'Б' => 'B', 'б' => 'b',
            'В' => 'V', 'в' => 'v',
            'Г' => 'G', 'г' => 'g',
            'Д' => 'D', 'д' => 'd',
            'Е' => 'E', 'е' => 'e',
            'Ж' => 'J', 'ж' => 'j',
            'З' => 'Z', 'з' => 'z',
            'И' => 'I', 'и' => 'i',
            'Й' => 'Y', 'й' => 'y',
            'К' => 'K', 'к' => 'k',
            'Л' => 'L', 'л' => 'l',
            'М' => 'M', 'м' => 'm',
            'Н' => 'N', 'н' => 'n',
            'О' => 'O', 'о' => 'o',
            'П' => 'P', 'п' => 'p',
            'Р' => 'R', 'р' => 'r',
            'С' => 'S', 'с' => 's',
            'Т' => 'T', 'т' => 't',
            'У' => 'U', 'у' => 'u',
            // Map both Х and Ҳ to Latin reach set
            'Х' => 'X', 'х' => 'x',
            'Ҳ' => 'H', 'ҳ' => 'h',
            'Қ' => 'Q', 'қ' => 'q',
            'Ф' => 'F', 'ф' => 'f',
            'Ц' => 'C', 'ц' => 'c',
            'Ь' => '',  'ь' => '',
            'Ъ' => '',  'ъ' => '',
        ];

        return strtr($text, $map);
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


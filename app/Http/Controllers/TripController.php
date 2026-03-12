<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramNotificationJob;
use App\Helpers\AddressHelper;
use App\Models\Commission;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{

    public function __construct(
        protected TripService $tripService,
    ) {}



    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'driver' && !$user->car) {
            return response()->json([
                'message' => 'Для создания поездки водитель должен добавить машину'
            ], 422);
        }

        $data = $request->validate([
            'from_lat' => 'nullable|numeric',
            'from_lng' => 'nullable|numeric',
            'from_address' => 'nullable|string',
            'to_lat' => 'nullable|numeric',
            'to_lng' => 'nullable|numeric',
            'to_address' => 'nullable|string',
            'date' => 'required|date',
            'time' => 'required',
            'amount' => 'nullable|integer|min:0',
            'seats' => 'nullable|integer|min:1',
            'comment' => 'nullable|string',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Проверка баланса (ТОЛЬКО для водителя)
        |--------------------------------------------------------------------------
        */
        if ($user->role === 'driver') {

            $amount = $data['amount'] ?? 0;
            $seats  = $data['seats'] ?? 1;

            // Максимальный возможный оборот
            $maxTotal = $amount * $seats;

            // 8% комиссия
            $maxCommission = round($maxTotal * 0.08, 2);

            if ($user->balance < $maxCommission) {
                return response()->json([
                    'has_balance' => false
                ], 422);
            }
        }

        $trip = Trip::create([
            'user_id' => $user->id,
            'role' => $user->role,
            'status' => 'active',

            // оригинальные адреса
            'from_address' => $data['from_address'] ?? null,
            'to_address'   => $data['to_address'] ?? null,

            // нормализованные адреса
            'from_address_normalized' => !empty($data['from_address'])
                ? $this->normalize($data['from_address'])
                : null,

            'to_address_normalized' => !empty($data['to_address'])
                ? $this->normalize($data['to_address'])
                : null,

            // координаты
            'from_lat' => $data['from_lat'] ?? null,
            'from_lng' => $data['from_lng'] ?? null,
            'to_lat'   => $data['to_lat'] ?? null,
            'to_lng'   => $data['to_lng'] ?? null,

            // основные поля
            'date'     => $data['date'],
            'time'     => $data['time'],
            'amount'   => $data['amount'] ?? null,
            'seats'    => $data['seats'] ?? 1,
            'comment'  => $data['comment'] ?? null,
        ]);

        return response()->json($trip, 201);
    }


    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);

        $map = [
            // кир → лат
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'x','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sh',
            'ы'=>'i','э'=>'e','ю'=>'yu','я'=>'ya',

            // узб
            'қ'=>'q','ғ'=>'g','ў'=>'o','ҳ'=>'h',
        ];

        $value = strtr($value, $map);

        // удаляем всё лишнее: ‘ ' ` - и т.д.
        $value = preg_replace('/[^a-z0-9\s]/u', '', $value);

        // убираем двойные пробелы
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }


    public function myTrips(Request $request)
    {
        $user = $request->user();

        return Trip::where('user_id', $user->id)
            ->where('role', 'driver')
            ->where('status', '!=', 'completed')
            ->with(['bookings.user'])
            ->latest()
            ->get();

    }
    public function myTripsForPassenger(Request $request)
    {
        $user = $request->user();

        return Trip::where('user_id', $user->id)
            ->where('role', 'passenger')
            ->where('status', '!=', 'completed')
            ->with(['bookings.user.car'])
            ->latest()
            ->get();
    }


    public function myCompleted(Request $request)
    {
        return $this->tripService
            ->getMyTripsByStatus($request->user(), 'completed');
    }

    public function activeTrips(Request $request)
    {
        $trips = Trip::query()
            ->where('status', 'active')
            ->where('role', 'passenger') // заказы пассажиров
            ->with('user:id,name,avatar,rating,rating_count')
            ->orderBy('date')
            ->paginate(10);

        return response()->json([
            'items' => $trips->items(),
            'pagination' => [
                'current'  => $trips->currentPage(),
                'previous' => $trips->previousPageUrl(),
                'next'     => $trips->nextPageUrl(),
                'total'    => $trips->total(),
            ],
        ]);
    }


    public function activeTripsForPassengers(Request $request)
    {
        $trips = Trip::query()
            ->where('role', 'driver')
            ->where('status', 'active')
            ->where('seats', '>', 0)
            ->with(['user.car'])
            ->orderBy('date')
            ->paginate(10);

        return response()->json([
            'items' => $trips->items(),
            'pagination' => [
                'current'  => $trips->currentPage(),
                'previous' => $trips->previousPageUrl(),
                'next'     => $trips->nextPageUrl(),
                'total'    => $trips->total(),
            ],
        ]);
    }

    /**
     * 6. завершить поездку
     */
    public function completed(Request $request, Trip $trip)
    {
        $driver = $request->user();

        abort_if($trip->status === 'completed', 422);
        abort_if($trip->role !== 'passenger', 422, 'Invalid trip type');

        DB::transaction(function () use ($trip, $driver) {

            $driverBooking = $trip->bookings()
                ->where('user_id', $driver->id)
                ->where('role', 'driver')
                ->first();

            abort_if(!$driverBooking, 403);

            $totalAmount = $trip->amount ?? 0;

            $percent = Setting::where('key', 'commission_percent')->value('value') ?? 8;

            $commission = round($totalAmount * ($percent / 100), 2);

            if ($commission > 0) {
                $driver->decrement('balance', $commission);

                Commission::create([
                    'trip_id' => $trip->id,
                    'booking_id' => $driverBooking->id,
                    'user_id' => $driver->id,
                    'total_amount' => $totalAmount,
                    'commission_percent' => $percent,
                    'commission_amount' => $commission,
                    'type' => 'passenger_trip',
                ]);
            }

            $trip->update(['status' => 'completed']);

            $trip->bookings()
                ->where('status', 'in_progress')
                ->update(['status' => 'completed']);
        });

        $passenger = User::find($trip->user_id);

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        $messagePassenger =
            "{$from} → {$to}\n" .
            "✅ Sizning zakazingiz yakunlandi!\n" .
            "✅ Ваша поездка завершилась.";

        if ($passenger?->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                $messagePassenger
            ));
        }

        return response()->json($trip->fresh());
    }

    public function completedIntercity(Request $request, Trip $trip)
    {
        $driver = $request->user();

        abort_if($trip->user_id !== $driver->id, 403);
        abort_if($trip->status === 'completed', 422);

        DB::transaction(function () use ($trip, $driver) {

            $totalAmount = $trip->bookings()
                ->where('status', 'in_progress')
                ->sum(DB::raw('offered_price * seats'));

            $percent = Setting::where('key', 'commission_percent')->value('value') ?? 8;

            $commission = round($totalAmount * ($percent / 100), 2);

            if ($commission > 0) {
                $driver->decrement('balance', $commission);

                Commission::create([
                    'trip_id' => $trip->id,
                    'booking_id' => null,
                    'user_id' => $driver->id,
                    'total_amount' => $totalAmount,
                    'commission_percent' => $percent,
                    'commission_amount' => $commission,
                    'type' => 'driver_trip',
                ]);
            }

            $trip->update(['status' => 'completed']);

            $trip->bookings()
                ->where('status', 'in_progress')
                ->update(['status' => 'completed']);
        });

        $trip->refresh();

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        $message =
            "{$from} → {$to}\n" .
            "✅ Sizning zakazingiz yakunlandi!\n" .
            "✅ Ваша поездка завершилась.";

        if ($driver->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $driver->telegram_chat_id,
                $message
            ));
        }

        return response()->json($trip);
    }


    /**
     * 7. Удалить поездку
     */
    public function destroy(Request $request, Trip $trip)
    {
        abort_if($trip->user_id !== $request->user()->id, 403);

        $passenger = $request->user();
        $booking = $trip->bookings()
            ->where('status', 'in_progress')
            ->with('user') // водитель
            ->first();

        $driver = $booking?->user;

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        // 📝 сообщение водителю
        $messageDriver =
            "{$from} → {$to}\n" .
            "Yo‘lovchi safarni bekor qildi\n" .
            "Пассажир отменил поездку";

        // 🔔 уведомляем водителя
        if ($driver && $driver->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $driver->telegram_chat_id,
                $messageDriver
            ));
        }

        // Удаляем поездку
        $trip->delete();

        return response()->json(['message' => 'Trip deleted']);
    }


    public function searchByAddress(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|string',
            'to'   => 'nullable|string',
            'date' => 'nullable|date',
        ]);

        $query = Trip::query()
            ->where('role', 'driver')
            ->where('status', 'active')
            ->where('seats', '>', 0)
            ->with(['user.car']);

        // 🔎 поиск ОТКУДА
        if (!empty($data['from'])) {
            $normalizedFrom = $this->normalize($data['from']);

            $query->where('from_address_normalized', 'LIKE', "%{$normalizedFrom}%");
        }

        // 🔎 поиск КУДА
        if (!empty($data['to'])) {
            $normalizedTo = $this->normalize($data['to']);

            $query->where('to_address_normalized', 'LIKE', "%{$normalizedTo}%");
        }

        // 📅 фильтр по дате (если передали)
        if (!empty($data['date'])) {
            $query->whereDate('date', $data['date']);
        }

        $trips = $query
            ->orderBy('date')
            ->orderBy('time')
            ->paginate(10);

        return response()->json([
            'items' => $trips->items(),
            'pagination' => [
                'current'  => $trips->currentPage(),
                'previous' => $trips->previousPageUrl(),
                'next'     => $trips->nextPageUrl(),
                'total'    => $trips->total(),
            ],
        ]);
    }


    public function searchByUserLocation(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $lat = $data['lat'];
        $lng = $data['lng'];
        $radius = 5; // км

        // bounding box
        $latRange = $radius / 111;
        $lngRange = $radius / (111 * cos(deg2rad($lat)));

        // формула Haversine
        $haversine = "
        6371 * acos(
            cos(radians(?))
            * cos(radians(from_lat))
            * cos(radians(from_lng) - radians(?))
            + sin(radians(?))
            * sin(radians(from_lat))
        )
    ";

        $trips = Trip::query()
            ->where('role', 'driver')
            ->where('status', 'active')
            ->where('seats', '>', 0)

            // только поездки с координатами
            ->whereNotNull('from_lat')
            ->whereNotNull('from_lng')

            // 👇 минимум 2 слова в адресе
            ->where('from_address_normalized', 'like', '% %')
            ->where('to_address_normalized', 'like', '% %')

            // быстрый фильтр
            ->whereBetween('from_lat', [$lat - $latRange, $lat + $latRange])
            ->whereBetween('from_lng', [$lng - $lngRange, $lng + $lngRange])

            // точный радиус
            ->select('trips.*')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])
            ->whereRaw("$haversine <= ?", [$lat, $lng, $lat, $radius])

            // 👇 ВАЖНО
            ->with(['user.car'])

            ->orderBy('distance')
            ->paginate(25);

        return response()->json([
            'items' => $trips->items(),
            'pagination' => [
                'current'  => $trips->currentPage(),
                'previous' => $trips->previousPageUrl(),
                'next'     => $trips->nextPageUrl(),
                'total'    => $trips->total(),
            ],
        ]);
    }


    public function searchPassengerByAddress(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|string|min:1',
            'to'   => 'nullable|string|min:1',
            'date' => 'nullable|date',
        ]);

        $query = Trip::query()
            ->where('role', 'passenger')
            ->where('status', 'active')
            ->with(['user']);

        // 🔎 ОТКУДА
        if (!empty($data['from'])) {
            $normalizedFrom = $this->normalize($data['from']);

            $query->where('from_address_normalized', 'LIKE', "%{$normalizedFrom}%");
        }

        // 🔎 КУДА
        if (!empty($data['to'])) {
            $normalizedTo = $this->normalize($data['to']);

            $query->where('to_address_normalized', 'LIKE', "%{$normalizedTo}%");
        }

        // 📅 дата (если указана)
        if (!empty($data['date'])) {
            $query->whereDate('date', $data['date']);
        }

        $trips = $query
            ->orderBy('date')
            ->orderBy('time')
            ->paginate(10);

        return response()->json([
            'items' => $trips->items(),
            'pagination' => [
                'current'  => $trips->currentPage(),
                'previous' => $trips->previousPageUrl(),
                'next'     => $trips->nextPageUrl(),
                'total'    => $trips->total(),
            ],
        ]);
    }
    public function searchPassengerByLocation(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $lat = $data['lat'];
        $lng = $data['lng'];
        $radius = 5; // км

        $latRange = $radius / 111;
        $lngRange = $radius / (111 * cos(deg2rad($lat)));

        $haversine = "
        6371 * acos(
            cos(radians(?))
            * cos(radians(from_lat))
            * cos(radians(from_lng) - radians(?))
            + sin(radians(?))
            * sin(radians(from_lat))
        )
    ";

        $trips = Trip::query()
            ->where('role', 'passenger')
            ->where('status', 'active')
            ->whereNotNull('from_lat')
            ->whereNotNull('from_lng')

            // 👇 минимум 2 слова в адресе
            ->where('from_address_normalized', 'like', '% %')
            ->where('to_address_normalized', 'like', '% %')

            ->whereBetween('from_lat', [$lat - $latRange, $lat + $latRange])
            ->whereBetween('from_lng', [$lng - $lngRange, $lng + $lngRange])

            ->select('trips.*')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])
            ->whereRaw("$haversine <= ?", [$lat, $lng, $lat, $radius])

            ->with(['user'])
            ->orderBy('distance')
            ->paginate(25);

        return response()->json([
            'items' => $trips->items(),
            'pagination' => [
                'current'  => $trips->currentPage(),
                'previous' => $trips->previousPageUrl(),
                'next'     => $trips->nextPageUrl(),
                'total'    => $trips->total(),
            ],
        ]);
    }



}

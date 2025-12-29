<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramNotificationJob;
use App\Helpers\AddressHelper;
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
            'amount' => 'nullable|integer',
            'seats' => 'nullable|integer|min:1',
            'comment' => 'nullable|string',
        ]);

        $trip = Trip::create([
            'user_id' => $user->id,
            'role' => $user->role,
            'status' => 'active',

            // оригинальные адреса
            'from_address' => $data['from_address'] ?? null,
            'to_address'   => $data['to_address'] ?? null,

            // НОРМАЛИЗОВАННЫЕ адреса
            'from_address_normalized' => !empty($data['from_address'])
                ? $this->normalize($data['from_address'])
                : null,

            'to_address_normalized' => !empty($data['to_address'])
                ? $this->normalize($data['to_address'])
                : null,

            // остальные поля
            'from_lat' => $data['from_lat'] ?? null,
            'from_lng' => $data['from_lng'] ?? null,
            'to_lat'   => $data['to_lat'] ?? null,
            'to_lng'   => $data['to_lng'] ?? null,
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
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e',
            'ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sh',
            'ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya',

            // узб
            'қ'=>'q','ғ'=>'g','ў'=>'o','ҳ'=>'h',

            // лат → единый вид
            'x' => 'kh', // buxara → bukhara
        ];

        return strtr($value, $map);
    }


    public function myTrips(Request $request)
    {
        $user = $request->user();

        return Trip::where('user_id', $user->id)
            ->where('role', 'driver')
            ->where('status', '!=', 'completed')
            ->with(['bookings.user', 'user.car'])
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
        $data = $request->validate([
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'nullable|numeric|max:50',
        ]);

        $lat = $data['lat'];
        $lng = $data['lng'];
        $radius = max(0.1, min($data['radius'] ?? 10, 50));

        // bounding box
        $latRange = $radius / 111;
        $lngRange = $radius / (111 * cos(deg2rad($lat)));

        // формула расстояния
        $haversine = "
        (6371 * acos(
            cos(radians(?)) *
            cos(radians(from_lat)) *
            cos(radians(from_lng) - radians(?)) +
            sin(radians(?)) *
            sin(radians(from_lat))
        ))
    ";
        return Trip::query()
            ->whereBetween('from_lat', [$lat - $latRange, $lat + $latRange])
            ->whereBetween('from_lng', [$lng - $lngRange, $lng + $lngRange])

            ->whereRaw("$haversine <= ?", [$lat, $lng, $lat, $radius])

            ->select('trips.*')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])

            ->where('status', 'active')
            ->where('role', '!=', 'driver')

            ->orderBy('distance')
            ->with('user:id,name,avatar,rating,rating_count')
            ->limit(200)
            ->get();
    }



//    public function activeTripsForPassengers(Request $request)
//    {
//        return Trip::where('role', 'driver')
//            ->where('status', '!=', 'completed')
//            ->where('seats', '>', 0)
//            ->with(['user.car'])
//            ->latest()
//            ->paginate(10);
//    }
    public function activeTripsForPassengers(Request $request)
    {
        $data = $request->validate([
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'nullable|numeric|max:50', // км
        ]);

        $lat = $data['lat'];
        $lng = $data['lng'];
        $radius = max(0.5, min($data['radius'] ?? 10, 50));

        // bounding box
        $latRange = $radius / 111;
        $lngRange = $radius / (111 * cos(deg2rad($lat)));

        // формула расстояния (Haversine)
        $haversine = "
        (6371 * acos(
            cos(radians(?)) *
            cos(radians(from_lat)) *
            cos(radians(from_lng) - radians(?)) +
            sin(radians(?)) *
            sin(radians(from_lat))
        ))
    ";

        return Trip::query()
            ->where('role', 'driver')
            ->where('status', '!=','completed')
            ->where('seats', '>', 0)

            // быстрый фильтр
            ->whereBetween('from_lat', [$lat - $latRange, $lat + $latRange])
            ->whereBetween('from_lng', [$lng - $lngRange, $lng + $lngRange])

            // точный радиус
            ->whereRaw("$haversine <= ?", [$lat, $lng, $lat, $radius])

            ->select('trips.*')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])

            ->with(['user.car'])
            ->orderBy('distance')
            ->limit(200)
            ->get();
    }

    /**
     * 6. завершить поездку
     */
    public function completed(Request $request, Trip $trip)
    {
        $driver = $request->user();

        $driverBooking = $trip->bookings()
            ->where('user_id', $driver->id)
            ->where('role', 'driver')
            ->first();

        abort_if(!$driverBooking, 403);

        abort_if($trip->status === 'completed', 422);

        DB::transaction(function () use ($trip) {

            $trip->update([
                'status' => 'completed',
            ]);

            $trip->bookings()
                ->where('status', 'in_progress')
                ->update(['status' => 'completed']);
        });

        $passenger = User::where('id', $trip->user_id)
            ->first();


        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        $messagePassenger =
            "{$from} → {$to}\n" .
            "✅ Sizning zakazingiz yakunlandi!\n" .
            "✅ Ваша поездка завершилась.";


        if ($passenger && $passenger->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                $messagePassenger
            ));
        }


        return response()->json($trip);
    }

    public function completedIntercity(Request $request, Trip $trip)
    {
        $driver = $request->user();

        abort_if($trip->user_id !== $driver->id, 403);
        abort_if($trip->status === 'completed', 422);

        DB::transaction(function () use ($trip) {
            DB::table('trips')
                ->where('id', $trip->id)
                ->update(['status' => 'completed']);

            DB::table('bookings')
                ->where('trip_id', $trip->id)
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

        $passengerChatIds = DB::table('bookings')
            ->join('users', 'users.id', '=', 'bookings.user_id')
            ->where('bookings.trip_id', $trip->id)
            ->where('bookings.role', 'passenger')
            ->whereNotNull('users.telegram_chat_id')
            ->pluck('users.telegram_chat_id');

        foreach ($passengerChatIds as $chatId) {
            dispatch(new SendTelegramNotificationJob(
                $chatId,
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

    /**
     * GET /api/trips/search
     */
    public function search(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|string|min:1',
            'to'   => 'nullable|string|min:1',
            'date' => 'nullable|date',
        ]);

        $query = Trip::query()
            ->where('role', 'driver')
            ->where('status', 'active')
            ->with(['bookings.user']);

        // FROM
        if (!empty($data['from'])) {
            $from = $this->normalize($data['from']);

            $query->where(
                'from_address_normalized',
                'LIKE',
                "%{$from}%"
            );
        }

        if (!empty($data['to'])) {
            $to = $this->normalize($data['to']);

            $query->where(
                'to_address_normalized',
                'LIKE',
                "%{$to}%"
            );
        }

        // DATE
        if (!empty($data['date'])) {
            $query->whereDate('date', $data['date']);
        }

        return $query->latest()->get();
    }


    public function searchPassengerOrders(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|string|min:1',
            'to'   => 'nullable|string|min:1',
            'date' => 'nullable|date',
        ]);

        $query = Trip::query()
            ->where('role', 'passenger')
            ->where('status', 'active')
            ->with(['user']); // пассажир (кто создал заказ)

        // FROM
        if (!empty($data['from'])) {
            $from = $this->normalize($data['from']);

            $query->where(
                'from_address_normalized',
                'LIKE',
                "%{$from}%"
            );
        }

        if (!empty($data['to'])) {
            $to = $this->normalize($data['to']);

            $query->where(
                'to_address_normalized',
                'LIKE',
                "%{$to}%"
            );
        }

        // DATE
        if (!empty($data['date'])) {
            $query->whereDate('date', $data['date']);
        }

        return $query->latest()->get();
    }

}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\Booking;
use App\Models\Car;
use App\Models\Commission;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPanelController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Пополнение / списание баланса
    |--------------------------------------------------------------------------
    | phone: 901234567
    | amount: 50000
    | action: add / subtract
    */
    public function updateBalance(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|size:9',
            'amount' => 'required|numeric|min:1',
            'action' => 'required|in:add,subtract',
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        DB::transaction(function () use ($user, $data) {

            if ($data['action'] === 'add') {
                $user->increment('balance', $data['amount']);
            }

            if ($data['action'] === 'subtract') {
                $user->decrement('balance', $data['amount']);
            }
        });

        $user->refresh();
        // Telegram уведомление
        if ($user->telegram_chat_id) {

            $message = "💵Balansingiz o'zgardi: {$user->balance} UZS\n" .
                "Ваш баланс изменился: {$user->balance} UZS";

            dispatch(new SendTelegramNotificationJob(
                $user->telegram_chat_id,
                $message
            ));
        }

        return response()->json([
            'message' => 'Balance updated successfully',
            'new_balance' => $user->balance,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Изменение роли пользователя
    |--------------------------------------------------------------------------
    | phone: 901234567
    | role: driver / passenger / admin
    */
    public function updateUserRole(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|size:9',
            'role'  => 'required|in:passenger,driver',
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        $user->update([
            'role' => $data['role']
        ]);

        return response()->json([
            'message' => 'Role updated successfully',
            'user' => $user,
        ]);
    }

    public function sendMessageAll(Request $request)
    {
        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'role' => 'nullable|in:driver,passenger',
        ]);

        $query = User::whereNotNull('telegram_chat_id');

        // Если передали role → фильтруем
        if (!empty($data['role'])) {
            $query->where('role', $data['role']);
        }

        $totalSent = 0;

        $query->chunk(500, function ($users) use ($data, &$totalSent) {

            foreach ($users as $user) {
                dispatch(new SendTelegramNotificationJob(
                    $user->telegram_chat_id,
                    $data['message']
                ));

                $totalSent++;
            }
        });

        if ($totalSent === 0) {
            return response()->json([
                'message' => 'No users found'
            ]);
        }

        return response()->json([
            'message' => 'Broadcast sent successfully',
            'total_sent' => $totalSent,
        ]);
    }

    public function sendToUser(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|size:9',
            'message' => 'required|string|max:2000',
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if (!$user->telegram_chat_id) {
            return response()->json([
                'message' => 'User has no Telegram connected'
            ], 422);
        }

        dispatch(new SendTelegramNotificationJob(
            $user->telegram_chat_id,
            $data['message']
        ));

        return response()->json([
            'message' => 'Message sent successfully',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Авто-завершение просроченных поездок
    |--------------------------------------------------------------------------
    | Завершает все поездки (in_progress), время которых было 2+ часа назад.
    | Снимает комиссию с водителя если есть бронь.
    */
    public function autoCompleteTrips()
    {
        $cutoff = Carbon::now('Asia/Tashkent')->subMinutes(10);

        $percent = (int) (Setting::where('key', 'commission_percent')->value('value') ?? 0);

        $trips = Trip::where('status', '!=', 'completed')
            ->where(DB::raw("CONCAT(date, ' ', time)"), '<=', $cutoff->format('Y-m-d H:i:s'))
            ->with('bookings')
            ->get();

        $completedCount = 0;
        $commissionCount = 0;

        foreach ($trips as $trip) {

            DB::transaction(function () use ($trip, $percent, &$completedCount, &$commissionCount) {

                // 🔒 защита от повторного списания
                $alreadyCharged = Commission::where('trip_id', $trip->id)->exists();

                // берём только реальные поездки
                $activeBookings = $trip->bookings->where('status', 'in_progress');

                if (!$alreadyCharged && $activeBookings->isNotEmpty()) {

                    // 💰 считаем сумму по типу трипа
                    if ($trip->role === 'driver') {
                        $totalAmount = $activeBookings->sum(fn($b) => $b->offered_price * $b->seats);
                    } else {
                        $totalAmount = $trip->amount ?? 0;
                    }

                    $commission = round($totalAmount * ($percent / 100), 2);

                    if ($commission > 0) {

                        if ($trip->role === 'driver') {
                            // водитель — создатель трипа
                            $driverId = $trip->user_id;
                            $bookingId = null;
                            $type = 'driver_trip';
                        } else {
                            // водитель — в booking
                            $driverBooking = $activeBookings->where('role', 'driver')->first();

                            $driverId = $driverBooking?->user_id;
                            $bookingId = $driverBooking?->id;
                            $type = 'passenger_trip';
                        }

                        if ($driverId) {
                            User::where('id', $driverId)->decrement('balance', $commission);

                            Commission::create([
                                'trip_id'             => $trip->id,
                                'booking_id'          => $bookingId,
                                'user_id'             => $driverId,
                                'total_amount'        => $totalAmount,
                                'commission_percent'  => $percent,
                                'commission_amount'   => $commission,
                                'type'                => $type,
                            ]);

                            $commissionCount++;
                        }
                    }
                }

                // ✅ завершаем trip
                $trip->update(['status' => 'completed']);

                // ✅ реальные поездки → completed
                $trip->bookings()
                    ->where('status', 'in_progress')
                    ->update(['status' => 'completed']);

                // ❌ офферы → cancelled
                $trip->bookings()
                    ->where('status', 'requested')
                    ->update(['status' => 'cancelled']);

                $completedCount++;
            });
        }

        return response()->json([
            'message' => 'Auto-complete finished',
            'trips_completed' => $completedCount,
            'commissions_charged' => $commissionCount,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 📋 Список пользователей с поиском
    |--------------------------------------------------------------------------
    */
    public function users(Request $request)
    {
        $query = User::with('car');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        $users = $query->orderByDesc('id')->paginate(20);

        return response()->json($users);
    }

    /*
    |--------------------------------------------------------------------------
    | 👤 Детали пользователя
    |--------------------------------------------------------------------------
    */
    public function userShow(User $user)
    {
        $user->load('car');

        $activeTrips = Trip::where('user_id', $user->id)
            ->where('status', '!=', 'completed')
            ->count();

        $totalTrips = Trip::where('user_id', $user->id)->count();

        $activeBookings = Booking::where('user_id', $user->id)
            ->where('status', '!=', 'completed')
            ->count();

        $totalBookings = Booking::where('user_id', $user->id)->count();

        return response()->json([
            'user' => $user,
            'stats' => [
                'active_trips' => $activeTrips,
                'total_trips' => $totalTrips,
                'active_bookings' => $activeBookings,
                'total_bookings' => $totalBookings,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ✏️ Обновить пользователя
    |--------------------------------------------------------------------------
    */
    public function userUpdate(Request $request, User $user)
    {
        $data = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'phone'   => 'sometimes|string|size:9',
            'role'    => 'sometimes|in:passenger,driver',
            'balance' => 'sometimes|integer',
        ]);

        $user->update($data);

        return response()->json($user->fresh()->load('car'));
    }

    /*
    |--------------------------------------------------------------------------
    | 🚗 Обновить машину пользователя
    |--------------------------------------------------------------------------
    */
    public function userUpdateCar(Request $request, User $user)
    {
        $data = $request->validate([
            'model'  => 'required|string|max:255',
            'color'  => 'required|string|max:255',
            'number' => 'required|string|max:20',
        ]);

        $car = Car::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return response()->json($car);
    }

    /*
    |--------------------------------------------------------------------------
    | 🗑️ Удалить машину пользователя
    |--------------------------------------------------------------------------
    */
    public function userDeleteCar(User $user)
    {
        Car::where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Car deleted']);
    }

    /*
    |--------------------------------------------------------------------------
    | 📋 Список поездок
    |--------------------------------------------------------------------------
    */
    public function trips(Request $request)
    {
        $query = Trip::with(['user:id,name,phone,role', 'bookings']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('from_address', 'like', "%{$search}%")
                  ->orWhere('to_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('phone', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($from = $request->query('from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('date', '<=', $to);
        }

        $trips = $query->orderByDesc('id')->paginate(20);

        return response()->json($trips);
    }

    /*
    |--------------------------------------------------------------------------
    | 🔍 Детали поездки
    |--------------------------------------------------------------------------
    */
    public function tripShow(Trip $trip)
    {
        $trip->load(['user.car', 'bookings.user']);

        return response()->json($trip);
    }

    /*
    |--------------------------------------------------------------------------
    | 🗑️ Удалить поездку
    |--------------------------------------------------------------------------
    */
    public function tripDelete(Trip $trip)
    {
        $trip->bookings()->delete();
        $trip->delete();

        return response()->json(['message' => 'Trip deleted']);
    }

    /*
    |--------------------------------------------------------------------------
    | 📋 Список бронирований
    |--------------------------------------------------------------------------
    */
    public function bookings(Request $request)
    {
        $query = Booking::with(['trip:id,from_address,to_address,date,time,status', 'user:id,name,phone,role']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($u) use ($search) {
                $u->where('phone', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $bookings = $query->orderByDesc('id')->paginate(20);

        return response()->json($bookings);
    }

    /*
    |--------------------------------------------------------------------------
    | 🗑️ Удалить бронирование
    |--------------------------------------------------------------------------
    */
    public function bookingDelete(Booking $booking)
    {
        $trip = $booking->trip;

        if ($booking->status === 'in_progress' && $trip) {
            $trip->increment('seats', $booking->seats);
            if ($trip->status === 'in_progress') {
                $trip->update(['status' => 'active']);
            }
        }

        $booking->delete();

        return response()->json(['message' => 'Booking deleted']);
    }
}

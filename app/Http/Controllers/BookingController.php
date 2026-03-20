<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\AddressHelper;

class BookingController extends Controller
{

    public function __construct(
        protected BookingService $bookingService
    ) {}
    /**
     * 1. Забронировать поездку / заказ
     *
     * Логика:
     * - passenger → бронирует поездку driver
     * - driver → бронирует заказ passenger
     */
    public function store(Request $request)
    {
        $driver = $request->user();

        $data = $request->validate([
            'trip_id' => 'required|exists:trips,id',
        ]);

        if (!$driver->car) {
            return response()->json([
                'message' => 'Iltimos oldin mashina qushing! Сначало добваьте машину'
            ], 423);
        }

        DB::transaction(function () use ($data, $driver, &$booking) {

            $trip = Trip::lockForUpdate()->findOrFail($data['trip_id']);
            // 🔒 блокируем строку чтобы два водителя не взяли заказ

            abort_if($trip->user_id === $driver->id, 423, 'Cannot take your own trip');
            abort_if($trip->status !== 'active', 423, 'Trip already taken');

            // 🔥 берем процент комиссии из БД
            $percent = (int) (Setting::where('key', 'commission_percent')->value('value') ?? 8);

            // считаем комиссию
            $commission = round(($trip->amount ?? 0) * ($percent / 100), 2);

            // 🚫 если не хватает на комиссию
//            if ($driver->balance < $commission) {
//                return response()->json([
//                    'balance_sufficient' => false,
//                    'required_commission' => $commission,
//                    'balance' => $driver->balance,
//                    'percent' => $percent,
//                ], 422);
//            }

            if ($driver->balance < $commission) {
                return response()->json([
                    'message' => 'Iltimos oldin mashina qushing! Сначало добваьте машину'
                ], 423);
            }

            // проверяем что водитель ещё не назначен
            $alreadyTaken = $trip->bookings()
                ->where('role', 'driver')
                ->exists();

            abort_if($alreadyTaken, 423, 'Driver already assigned');

            $booking = Booking::create([
                'trip_id'       => $trip->id,
                'user_id'       => $driver->id,
                'seats'         => $trip->seats,
                'role'          => 'driver',
                'offered_price' => $trip->amount, // фиксируем цену
                'status'        => 'in_progress',
            ]);

            $trip->update(['status' => 'in_progress']);
        });

        // уведомления после transaction
        $trip = $booking->trip;
        $passenger = User::find($trip->user_id);

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        $messagePassenger =
            "{$from} → {$to}\nHaydovchi topildi, mening zakazlarim bo'limida haydovchi b-n bog'lanishingiz mumkin!\nВодитель нашелся, в разделе мои заказы можете созвониться с водителем!";

        $messageDriver =
            "{$from} → {$to}\nYo‘lovchi sizni kutmoqda, mening bronlarim bo'limida yo'lovchi b-n bog'lanishingiz mumkin!\nПассажир ждет вас, в разделе мои бронирование можете созвониться с пассажиром!";

        if ($passenger?->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                $messagePassenger
            ));
        }

        if ($driver->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $driver->telegram_chat_id,
                $messageDriver
            ));
        }

        return response()->json($booking, 201);
    }


    public function storeForPassenger(Request $request)
    {
        $passenger = $request->user();

        $data = $request->validate([
            'trip_id'       => 'required|exists:trips,id',
            'seats'         => 'nullable|integer|min:1',
            'offered_price' => 'nullable|numeric|min:0',
        ]);

        $seats = $data['seats'] ?? 1;

        $hasOffer = array_key_exists('offered_price', $data) && !is_null($data['offered_price']);

        DB::transaction(function () use (
            $data,
            $passenger,
            $seats,
            $hasOffer,
            &$booking
        ) {

            // 🔒 блокируем поездку
            $trip = Trip::lockForUpdate()
                ->where('id', $data['trip_id'])
                ->where('role', 'driver')
                ->firstOrFail();

            // ❌ повторная бронь
            abort_if(
                Booking::where('trip_id', $trip->id)
                    ->where('user_id', $passenger->id)
                    ->exists(),
                403,
                'Siz bu zakazni bron qilgansiz'
            );

            // ❌ если обычная бронь — проверяем места
            if (!$hasOffer) {
                abort_if($trip->seats < $seats, 422, 'Joy yetarli emas');
            }

            // 💰 цена
            if ($hasOffer) {
                // пользователь может указать цену за всех
                $pricePerSeat = $data['offered_price'] / $seats;
            } else {
                $pricePerSeat = $trip->amount;
            }

            $booking = Booking::create([
                'trip_id'       => $trip->id,
                'user_id'       => $passenger->id,
                'seats'         => $seats,
                'role'          => 'passenger',
                'status'        => $hasOffer ? 'requested' : 'in_progress',
                'offered_price' => $pricePerSeat,
            ]);

            // уменьшаем места только при прямой брони
            if (!$hasOffer) {
                $trip->decrement('seats', $seats);
            }
        });

        $trip = $booking->trip;
        $driver = User::find($trip->user_id);

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        if ($hasOffer) {

            $messageDriver =
                "{$from} → {$to}\n" .
                "💰Yo‘lovchi {$seats} joy uchun {$data['offered_price']} taklif qildi. Buyurtmalarim bo'limidan tasdiqlang yoki rad eting.\n" .
                "Пассажир предлагает за {$seats} место {$data['offered_price']}. В разделе мои заказы подтвердите или отмените.";

            $messagePassenger =
                "⏳Sizning taklifingiz junatildi. Haydovchi javobini kuting.\n" .
                "Ваше предложение отправлено. Ждите ответ водителя.";

        } else {

            $messageDriver =
                "{$from} → {$to}\n" .
                "✅Yangi yo‘lovchi {$seats} joy bron qildi. Buyurtmalarim bo'limidan yo'lovchi b-n bog'lanishingiz mumkin\n" .
                "Новый пассажир забронировал {$seats} место. В разделе мои заказы можете созвониться с пассажиром";

            $messagePassenger =
                "✅Siz {$from} → {$to}\n{$seats} ta joy bron qildingiz! Bronlarim bo'limidan haydovchi b-n bog'lanishingiz mumkin\n" .
                "Вы забронировали {$seats} место! В разделе мои бронирование можете созвониться с водителем";
        }

        // 🔔 уведомляем водителя
        if ($driver && $driver->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $driver->telegram_chat_id,
                $messageDriver
            ));
        }

        // 🔔 уведомляем пассажира
        if ($passenger->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                $messagePassenger
            ));
        }

        return response()->json($booking, 201);
    }

    public function accept(Request $request, Booking $booking)
    {
        $driver = $request->user();

        $trip = Trip::findOrFail($booking->trip_id);

        // ❌ только владелец поездки
        abort_if($trip->user_id !== $driver->id, 403);

        // ❌ можно принимать только requested
        abort_if($booking->status !== 'requested', 422, 'Invalid booking status');

        // ❌ если мест уже не хватает
        abort_if($trip->seats < $booking->seats, 422, 'Not enough seats');

        DB::transaction(function () use ($booking, $trip) {

            $booking->update([
                'status' => 'in_progress',
            ]);

            // уменьшаем места
            $trip->decrement('seats', $booking->seats);
        });

        // 🔔 пассажир
        $passenger = User::find($booking->user_id);

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        if ($passenger && $passenger->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                "{$from} → {$to}\n" .
                "✅ Haydovchi sizning taklifingizni qabul qildi! Bronlarim bo'limidan haydovchi b-n bog'lanishingiz mumkin\n" .
                "Водитель принял вашу предложение! В разделе мои бронирование можете созвониться с водителем"
            ));
        }

        return response()->json([
            'message' => 'Booking accepted',
            'booking' => $booking->fresh()
        ]);
    }

    public function delete(Request $request, Booking $booking)
    {
        $driver = $request->user();

        $trip = Trip::findOrFail($booking->trip_id);

        // ❌ только владелец поездки
        abort_if($trip->user_id !== $driver->id, 403);

        // ❌ только requested
        abort_if($booking->status !== 'requested', 422, 'Invalid booking status');

        $passenger = User::find($booking->user_id);

        DB::transaction(function () use ($booking) {
            $booking->delete();
        });

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        // 🔔 пассажир
        if ($passenger && $passenger->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                "{$from} → {$to}\n" .
                "❌ Haydovchi sizning taklifingizni rad etdi.\n" .
                "Водитель отклонил ваше ценовое предложение.\n"
            ));
        }

        return response()->json([
            'message' => 'Booking rejected'
        ]);
    }




    public function myInProgressForPassengers(Request $request)
    {
        $user = $request->user();
        return  Booking::where('role', 'passenger')
            ->where('status', '!=', 'completed')
            ->where('user_id', $user->id)
            ->with('trip.user.car')
            ->latest()
            ->get();

    }

    /**
     * 2. Отменить бронь
     */
    public function cancel(Request $request, Booking $booking)
    {
        // только владелец брони
        abort_if($booking->user_id !== $request->user()->id, 403);
        $trip = Trip::where('id', $booking->trip_id)->first();
        $booking->delete();
        $trip->update(['status' => 'active']);



        $passenger = User::find($trip->user_id);
        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        $message =
            "{$from} → {$to}\n" .
            "❌ Haydovchi o'z bronini bekor qildi, boshqa haydovchi qidirilmoqda.\n" .
            "❌ Водитель отменил свой бронь, идёт поиск другого водителя.";


        if ($passenger && $passenger->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                $message
            ));
        }

        return response()->json([
            'message' => 'Бронь отменена'
        ]);
    }
    public function cancelForPassengers(Request $request, Booking $booking)
    {
        if ($booking->user_id !== $request->user()->id) {
            abort(403);
        }
        $trip = Trip::where('id', $booking->trip_id)->first();
        $trip->increment('seats', $booking->seats);
        $trip->update(['status' => 'active']);
        $booking->delete();


        $passenger = User::find($trip->user_id);
        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        $message =
            "{$from} → {$to}\n" .
            "❌ Yo'lovchi o'z bronini bekor qildi, boshqa yo'lovchi qidirilmoqda.\n" .
            "❌ Пассажир отменил бронь, идёт поиск другого пассажира.";


        if ($passenger && $passenger->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                $message
            ));
        }

        return response()->json([
            'message' => 'Бронь отменена'
        ]);
    }

    /**
     * 3. Мои запросы in_progress
     */
    public function myInProgress(Request $request)
    {
        $user = $request->user();
        return Booking::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->where('role', 'driver')
            ->with('trip.user')
            ->get();
    }


    /**
     * 4. Мои запросы completed
     */
    public function myCompleted(Request $request)
    {
        return $this->bookingService
            ->getMyBookingsByStatus(
                $request->user(),
                'completed'
            );
    }


}

<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\Booking;
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
        $user = $request->user();

        $data = $request->validate([
            'trip_id' => 'required|exists:trips,id',
            'seats' => 'nullable|integer|min:1',
        ]);

        $trip = Trip::findOrFail($data['trip_id']);

        abort_if($trip->user_id === $user->id, 422);

        $booking = Booking::create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'seats'   => $data['seats'] ?? 1,
            'role'    => $user->role, // driver
            'status'  => 'in_progress',
        ]);

        $trip->update(['status' => 'in_progress']);

        // 👤 пассажир — владелец trip
        $passenger = User::find($trip->user_id);
        $driver = $user;

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        // 📝 сообщения
        $messagePassenger =
            "{$from} → {$to}\n" .
            "Haydovchi topildi, mening zakazlarim bo‘limida ko‘rishingiz mumkin!\n" .
            "Водитель нашелся, можете посмотреть в разделе мои заказы";

        $messageDriver =
            "{$from} → {$to}\n" .
            "Yo‘lovchi sizni kutmoqda, mening bronlarim bo'limida ko'rishingiz mumkin!\n" .
            "Пассажир ждет вас, можете посмотреть в разделе мои брони";


        // 🔔 уведомляем пассажира
        if ($passenger && $passenger->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                $messagePassenger
            ));
        }

        // 🔔 уведомляем водителя
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
        $offeredPrice = $data['offered_price'] ?? null;

        // поездка водителя
        $trip = Trip::where('id', $data['trip_id'])
            ->where('role', 'driver')
            ->firstOrFail();

        // ❌ если мест не хватает (ТОЛЬКО если сразу бронируем)
        if (!$offeredPrice) {
            abort_if($trip->seats < $seats, 422, 'Not enough seats');
        }

        // ❌ повторная бронь
        abort_if(
            Booking::where('trip_id', $trip->id)
                ->where('user_id', $passenger->id)
                ->exists(),
            403,
            'You already booked this trip'
        );

        DB::transaction(function () use (
            $trip,
            $passenger,
            $seats,
            $offeredPrice,
            &$booking
        ) {
            $booking = Booking::create([
                'trip_id'       => $trip->id,
                'user_id'       => $passenger->id,
                'seats'         => $seats,
                'role'          => 'passenger',
                'status'        => $offeredPrice ? 'requested' : 'in_progress',
                'offered_price' => $offeredPrice,
            ]);

            // уменьшаем места ТОЛЬКО при прямой брони
            if (!$offeredPrice) {
                $trip->decrement('seats', $seats);
            }
        });

        // 👤 водитель
        $driver = User::find($trip->user_id);

        $from = AddressHelper::short($trip->from_address);
        $to   = AddressHelper::short($trip->to_address);

        // 📝 сообщения
        if ($offeredPrice) {

            // 💰 предложение цены
            $messageDriver =
                "💰Yangi narx taklifi!\n" .
                "{$from} → {$to}\n" .
                "Yo‘lovchi {$seats} joy uchun {$offeredPrice} taklif qildi.\n" .
                "Iltimos, tasdiqlang yoki rad eting.";

            $messagePassenger =
                "⏳Sizning narx taklifingiz yuborildi.\n" .
                "Haydovchi javobini kuting.";

        } else {

            // ✅ обычная бронь
            $messageDriver =
                "{$from} → {$to}\n" .
                "Yangi yo‘lovchi topildi! {$seats} joy bron qildi.\n" .
                "Мои активные поездки bo‘limidan ko‘rishingiz mumkin.";

            $messagePassenger =
                "Bron tasdiqlandi!\n" .
                "{$from} → {$to}\n" .
                "Haydovchi xabardor qilindi.";
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
                "✅ Haydovchi sizning narx taklifingizni qabul qildi!\n" .
                "✅ Водитель принял вашу предложение"
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
                "❌ Haydovchi sizning narx taklifingizni rad etdi.\n" .
                "❌ Водитель отклонил ваше ценовое предложение.\n"
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

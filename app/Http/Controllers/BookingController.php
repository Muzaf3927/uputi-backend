<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // 📝 сообщения
        $messagePassenger = "$trip->from_address -> $trip->to_address Haydovchi topildi, mening zakazlarim bo‘limida ko‘rishingiz mumkin!
        Водитель нашелся, можете посмотреть в разделе мои заказы";

        $messageDriver = "{$trip->from_address} → {$trip->to_address} Yo‘lovchi sizni kutmoqda, mening bronlarim bo'limida ko'rishingiz mumkin!
            Пассажир ждет вас, можете посмотреть в разделе мои брони ";


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
            'trip_id' => 'required|exists:trips,id',
            'seats'   => 'nullable|integer|min:1',
        ]);

        $seats = $data['seats'] ?? 1;

        // поездка водителя
        $trip = Trip::where('id', $data['trip_id'])
            ->where('role', 'driver')
            ->firstOrFail();

        // ❌ если мест не хватает
        abort_if($trip->seats < $seats, 422, 'Not enough seats');

        // ❌ повторная бронь
        abort_if(
            Booking::where('trip_id', $trip->id)
                ->where('user_id', $passenger->id)
                ->exists(),
            403,
            'You already booked this trip'
        );

        DB::transaction(function () use ($trip, $passenger, $seats, &$booking) {

            $booking = Booking::create([
                'trip_id' => $trip->id,
                'user_id' => $passenger->id,
                'seats'   => $seats,
                'role'    => 'passenger',
                'status'  => 'in_progress',
            ]);

            // уменьшаем доступные места
            $trip->decrement('seats', $seats);
        });



        // 👤 водитель (владелец поездки)
        $driver = User::find($trip->user_id);

        // 📝 сообщения
        $messageDriver = "$trip->from_address -> $trip->to_address Yangi yo‘lovchi topildi! $seats joy bron qildi, Akitivniy safarlarim bo'limidan ko'rishingiz mumkin!
        Нашелся новый пассажир! Забронировал $seats место, можете посмотреть в разделе мои активные поездки ";

        $messagePassenger =
            "✅ Bron tasdiqlandi!\n" .
            "{$trip->from_address} → {$trip->to_address}\n" .
            "Haydovchi xabardor qilindi.";

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
        $message = "$trip->from_address -> $trip->to_address Haydovchi bekor qildi, boshqa haydovchi qidirilmoqda!
        Водитель отменил свой брон, ищется другой водитель! ";

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
        $message = "$trip->from_address -> $trip->to_address Yo'lovchi o'z bronini bekor qildi, boshqa yo'lovchi qidirilmoqda!
        Пассажир отменил свой брон, ищется другой пассажир! ";

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

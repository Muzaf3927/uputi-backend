<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\DriverOffer;
use App\Models\PassengerRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverOfferController extends Controller
{
    // Создать оффер водителя на запрос пассажира
    public function store(Request $request, PassengerRequest $passengerRequest)
    {
        $request->validate([
            'carModel' => 'required|string|max:255',
            'carColor' => 'required|string|max:255',
            'numberCar' => ['required', 'regex:/^[0-9]{2}[A-Z]{1}[0-9]{3}[A-Z]{2}$/'],
            'price' => 'nullable|integer|min:0',
        ], [
            'numberCar.regex' => 'Car number must be in format 01A000AA (only digits and uppercase Latin letters).'
        ]);

        // Проверяем, что запрос активен
        if ($passengerRequest->status !== 'active') {
            return response()->json([
                'message' => 'Passenger request is not active'
            ], 422);
        }

        // Проверяем, что водитель не создает оффер на свой запрос
//        if ($passengerRequest->user_id === Auth::id()) {
//            return response()->json([
//                'message' => 'You cannot create an offer on your own request'
//            ], 422);
//        }

        // Проверяем, нет ли уже оффера от этого водителя
        $existingOffer = DriverOffer::where('passenger_request_id', $passengerRequest->id)
            ->where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingOffer) {
            return response()->json([
                'message' => 'You have already created an offer for this request'
            ], 422);
        }

        $offer = DriverOffer::create([
            'passenger_request_id' => $passengerRequest->id,
            'user_id' => Auth::id(),
            'carModel' => $request->carModel,
            'carColor' => $request->carColor,
            'numberCar' => $request->numberCar,
            'price' => $request->price,
            'status' => 'pending',
        ]);

        // Создаем уведомление для пассажира
        $passenger = User::find($passengerRequest->user_id);
        $driver = Auth::user();

        $fromAddress = $passengerRequest->from_address ?? 'Unknown';
        $toAddress = $passengerRequest->to_address ?? 'Unknown';
        $priceText = $request->price ? " {$request->price} so'm" : '';

        $message = "{$driver->name} sizning so'rovingizga taklif yubordi{$priceText}. So'rovlar bo'limidan qabul qiling yoki rad eting";

        Notification::create([
            'user_id' => $passengerRequest->user_id,
            'sender_id' => Auth::id(),
            'type' => 'new_driver_offer',
            'message' => $message,
            'data' => json_encode([
                'passenger_request_id' => $passengerRequest->id,
                'driver_offer_id' => $offer->id,
                'driver_id' => Auth::id(),
                'price' => $offer->price,
            ]),
        ]);

        // Telegram уведомление
        if ($passenger && $passenger->telegram_chat_id) {
            dispatch(new SendTelegramNotificationJob(
                $passenger->telegram_chat_id,
                $message
            ));
        }

        return response()->json([
            'message' => 'Driver offer created!',
            'offer' => $offer->load('driver:id,name,phone,rating'),
        ], 201);
    }

    // Мои офферы (как водитель)
    public function myOffers(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $offers = DriverOffer::where('user_id', Auth::id())
            ->with(['passengerRequest.passenger:id,name,phone,rating'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($offers);
    }

    // Обновить оффер (изменить статус - принять/отклонить)
    public function update(Request $request, DriverOffer $driverOffer)
    {
        $user = Auth::user();
        $passengerRequest = $driverOffer->passengerRequest;

        // Только пассажир может обновлять статус оффера
        if ($user->id !== $passengerRequest->user_id) {
            return response()->json(['message' => 'No access'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:accepted,declined',
        ]);

        $oldStatus = $driverOffer->status;
        $newStatus = $validated['status'];

        // Если оффер уже был принят или отклонен, нельзя изменить
        if ($oldStatus !== 'pending') {
            return response()->json([
                'message' => 'Offer status cannot be changed'
            ], 422);
        }

        $driverOffer->update(['status' => $newStatus]);

        // Если оффер принят, отклоняем все остальные офферы на этот запрос
        if ($newStatus === 'accepted') {
            DriverOffer::where('passenger_request_id', $passengerRequest->id)
                ->where('id', '!=', $driverOffer->id)
                ->where('status', 'pending')
                ->update(['status' => 'declined']);

            // Помечаем запрос как выполненный
            $passengerRequest->update(['status' => 'completed']);

            // Уведомление водителю о принятии оффера
            $driver = User::find($driverOffer->user_id);
            $fromAddress = $passengerRequest->from_address ?? 'Unknown';
            $toAddress = $passengerRequest->to_address ?? 'Unknown';

            Notification::create([
                'user_id' => $driverOffer->user_id,
                'sender_id' => $passengerRequest->user_id,
                'type' => 'offer_accepted',
                'message' => "Sizning taklifingiz qabul qilindi! {$fromAddress} → {$toAddress}",
                'data' => json_encode([
                    'passenger_request_id' => $passengerRequest->id,
                    'driver_offer_id' => $driverOffer->id,
                ]),
            ]);

            // Telegram уведомление водителю
            if ($driver && $driver->telegram_chat_id) {
                $text = "✅ Sizning taklifingiz qabul qilindi!\n"
                    . "{$fromAddress} → {$toAddress}\n"
                    . "Sana: {$passengerRequest->date}, Vaqt: {$passengerRequest->time}";

                dispatch(new SendTelegramNotificationJob(
                    $driver->telegram_chat_id,
                    $text
                ));
            }
        } else {
            // Уведомление водителю об отклонении
            $driver = User::find($driverOffer->user_id);
            $fromAddress = $passengerRequest->from_address ?? 'Unknown';
            $toAddress = $passengerRequest->to_address ?? 'Unknown';

            Notification::create([
                'user_id' => $driverOffer->user_id,
                'sender_id' => $passengerRequest->user_id,
                'type' => 'offer_declined',
                'message' => "Sizning taklifingiz rad etildi. {$fromAddress} → {$toAddress}",
                'data' => json_encode([
                    'passenger_request_id' => $passengerRequest->id,
                    'driver_offer_id' => $driverOffer->id,
                ]),
            ]);
        }

        return response()->json([
            'message' => 'Offer status updated',
            'offer' => $driverOffer->load('driver:id,name,phone,rating'),
        ]);
    }

    // Отменить оффер (водитель отменяет свой оффер)
    public function delete(DriverOffer $driverOffer)
    {
        if ($driverOffer->user_id !== Auth::id()) {
            return response()->json(['message' => 'No access'], 403);
        }

        if ($driverOffer->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot cancel offer in this status'
            ], 422);
        }

        $driverOffer->delete();

        return response()->json([
            'message' => 'Offer delete successfully',
        ]);
    }
}

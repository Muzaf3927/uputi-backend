<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\TelegramConnectController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\PassengerRequestController;
use App\Http\Controllers\DriverOfferController;

Route::get('/test', function () {
    return 'test';
});


Route::post('count/download', [DownloadController::class, 'store']);

Route::post('/auth/start', [AuthController::class, 'start'])->middleware('sms.throttle');
Route::post('/auth/verify', [AuthController::class, 'verify']);

//удаление отделный
Route::post('/account/delete/send-otp', [AccountDeletionController::class, 'sendOtp']);
Route::post('/account/delete/verify', [AccountDeletionController::class, 'verifyAndDelete']);

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);


// Защищённые маршруты
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/telegram/connect', [TelegramConnectController::class, 'connect']);

    Route::get('/user', [UserController::class, 'me']); //Получения данных пользователя
    Route::post('/user', [UserController::class, 'update']);
    //Trips - Поездки
    Route::post('/trip', [TripController::class, 'store']); // создать
    Route::get('/my-trips', [TripController::class, 'myTrips']); //мои поездки
    Route::get('/trips', [TripController::class, 'index']); //все поездки
    Route::post('/trips/{trip}', [TripController::class, 'update']);   // обновить поездку
    Route::delete('/trips/{trip}', [TripController::class, 'destroy']); // удалить поездку
    Route::post('/trips/{trip}/complete', [TripController::class, 'complete']); // завершить поездку
    Route::get('/trips/completed/mine', [TripController::class, 'myCompletedTrips']); // мои завершенные поездки (как водитель)
    Route::get('/trips/completed/as-passenger', [TripController::class, 'myCompletedTripsAsPassenger']); // завершенные поездки, где я пассажир
    //Bookings - Броны

    Route::post('/trips/{trip}/booking', [BookingController::class, 'store']);// zabronirovat poezdku
    Route::post('/bookings/{booking}', [BookingController::class, 'update']);// obnovit status bronirovaniya (naprimer, prinyat ili otklonit)
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);// otmenit moe bronirovanie (passazhir otmenyaet)

    Route::get('/bookings/trip/{trips}', [BookingController::class, 'show']);
    // 4 отдельных API для разделов bookings
    Route::get('/bookings/my/confirmed', [BookingController::class, 'myConfirmedBookings']);// 1. moi bronirovaniya (confirmed)
    Route::get('/bookings/my/pending', [BookingController::class, 'myPendingBookings']);// 2. moi zaprosy (pending)
    Route::get('/bookings/to-my-trips/confirmed', [BookingController::class, 'confirmedBookingsToMyTrips']);// 3. zayavki na moi poezdki (confirmed)
    Route::get('/bookings/to-my-trips/pending', [BookingController::class, 'pendingBookingsToMyTrips']);// 4. zayavki na moi poezdki (pending)
    Route::get('/bookings/unread-count', [BookingController::class, 'unreadCount']);// количество непрочитанных для каждого раздела
    //Messages - Чаты
    Route::post('/chats/{trip}/send', [ChatController::class, 'sendMessage']); //отправить сообщение
    Route::get('/chats/{trip}/with/{receiver}', [ChatController::class, 'getChatMessages']); //получить чат
    Route::get('/chats', [ChatController::class, 'getUserChats']); //все чаты пользователя
    Route::get('/chats/unread-count', [ChatController::class, 'unreadCount']); //непрочитанные сообщение
    //Notifications - Уведомление
    Route::get('/notifications', [NotificationController::class, 'index']); // все уведомления
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']); // количество непрочитанных
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']); // отметить как прочитанное
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']); // прочитать все

    //Ratings - Оценка
    Route::post('/ratings/{trip}/to/{toUser}', [RatingController::class, 'rateUser']);  //поставить оценку
    Route::get('/ratings/user/{user}', [RatingController::class, 'getUserRatings']);  //отзывы пользователя
    Route::get('/ratings/given', [RatingController::class, 'getMyRatingsGiven']); //мои отзывы

    //Passenger Requests - Запросы пассажиров
    Route::post('/passenger-requests', [PassengerRequestController::class, 'store']); // создать запрос
    Route::get('/passenger-requests/my', [PassengerRequestController::class, 'myRequests']); // мои запросы
    Route::get('/passenger-requests', [PassengerRequestController::class, 'index']); // все запросы
    Route::post('/passenger-requests/{passengerRequest}', [PassengerRequestController::class, 'update']); // обновить запрос
    Route::delete('/passenger-requests/{passengerRequest}', [PassengerRequestController::class, 'destroy']); // удалить запрос
    Route::get('/passenger-requests/{passengerRequest}/offers', [PassengerRequestController::class, 'getOffers']); // получить офферы на запрос

    //Driver Offers - Офферы водителей
    Route::post('/passenger-requests/{passengerRequest}/offer', [DriverOfferController::class, 'store']); // создать оффер на запрос
    Route::get('/driver-offers/my', [DriverOfferController::class, 'myOffers']); // мои офферы (как водитель)
    Route::post('/driver-offers/{driverOffer}', [DriverOfferController::class, 'update']); // обновить оффер (изменить статус)
    Route::post('/driver-offers/{driverOffer}/delete', [DriverOfferController::class, 'delete']); // отменить оффер

    //Profile
    Route::get('/users/me', [UserController::class, 'me']);
    Route::get('/users/{user}', [UserController::class, 'user']);
    Route::delete('/user/delete-account', [UserController::class, 'deleteAccount']); //удалить аккаунт

    //Выйти
    Route::post('/logout', [AuthController::class, 'logout']); //выход

});

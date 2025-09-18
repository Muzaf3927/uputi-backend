<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'phone'    => 'required|size:9',
            'password' => 'required',
        ], [
            'phone.required' => 'Номер телефона обязателен',
            'phone.size'     => 'Номер телефона должен состоять из 9 цифр',
            'password.required' => 'Пароль обязателен',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Неверный логин или пароль'], 401);
        }

        // Удаляем старые токены
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Вход выполнен успешно',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ]);
    }

    // 1-й шаг: отправляем SMS для сброса пароля
    public function resetPasswordStepOne(Request $request)
    {
        $request->validate([
            'phone' => 'required|size:9|exists:users,phone',
        ], [
            'phone.required' => 'Номер телефона обязателен',
            'phone.size'     => 'Номер телефона должен состоять из 9 цифр',
            'phone.exists'   => 'Пользователь с таким номером не найден',
        ]);

        $user = User::where('phone', $request->phone)->first();

        // Генерируем verification_id
        $verificationId = Str::uuid();
        $ttl = now()->addMinutes(10);

        Cache::put('reset_user_' . $verificationId, $user->id, $ttl);
        Cache::put('reset_user_' . $verificationId . '_attempts', 0, $ttl);

        // Отправляем SMS (пока тестовый текст)
        $response = Http::withToken(env('ESKIZ_TOKEN'))
            ->asForm()
            ->post('https://notify.eskiz.uz/api/message/sms/send', [
                'mobile_phone' => '998' . $request->phone,
                'message' => "Parolni tiklash uchun kod: 123456", // пока тест, потом можно рандомный
                'from' => '4546',
            ]);

        if ($response->failed()) {
            Cache::forget('reset_user_' . $verificationId);
            Cache::forget('reset_user_' . $verificationId . '_attempts');
            return response()->json(['message' => 'Не удалось отправить SMS, попробуйте снова'], 500);
        }

        return response()->json([
            'message' => 'На ваш номер отправлено SMS для подтверждения',
            'verification_id' => $verificationId,
        ]);
    }


// 2-й шаг: проверяем код и меняем пароль
    public function resetPasswordStepTwo(Request $request)
    {
        $request->validate([
            'verification_id' => 'required|uuid',
            'message' => 'required|string',
            'password' => 'required|min:6|confirmed',
        ], [
            'password.required'  => 'Пароль обязателен',
            'password.min'       => 'Пароль должен содержать минимум 6 символов',
            'password.confirmed' => 'Пароли не совпадают',
        ]);

        $key = 'reset_user_' . $request->verification_id;
        $attemptsKey = $key . '_attempts';

        $userId = Cache::get($key);

        if (!$userId) {
            return response()->json(['message' => 'Срок подтверждения истёк или запрос не найден'], 422);
        }

        $user = User::find($userId);
        if (!$user) {
            Cache::forget($key);
            Cache::forget($attemptsKey);
            return response()->json(['message' => 'Пользователь не найден'], 422);
        }

        // Проверка тестового текста
        if ($request->message !== 'Parolni tiklash uchun kod: 123456') {
            $attempts = Cache::increment($attemptsKey);

            if ($attempts >= 3) {
                Cache::forget($key);
                Cache::forget($attemptsKey);
                return response()->json(['message' => 'Превышено количество попыток. Попробуйте снова'], 422);
            }

            return response()->json(['message' => 'Неверный код подтверждения. Попробуйте снова'], 422);
        }

        // Всё ок → обновляем пароль
        $user->password = Hash::make($request->password);
        $user->save();

        Cache::forget($key);
        Cache::forget($attemptsKey);

        return response()->json([
            'message' => 'Пароль успешно обновлён',
            'success' => true,
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вы вышли из системы']);
    }
}

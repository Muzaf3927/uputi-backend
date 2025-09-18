<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;


class VerifyController extends Controller
{
    public function registerStepOne(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|size:9|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'phone.unique' => 'Вы раньше зарегистрировались с этим номером',
        ]);

        // Создаём временного пользователя
        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Генерируем verification_id
        $verificationId = Str::uuid();

        // TTL для подтверждения (10 минут)
        $ttl = now()->addMinutes(10);

        // Сохраняем ID пользователя в кэш
        Cache::put('pending_user_' . $verificationId, $user->id, $ttl);

        // Инициализируем счётчик попыток (0)
        Cache::put('pending_user_' . $verificationId . '_attempts', 0, $ttl);

        // Отправка тестового SMS
        $response = Http::withToken(env('ESKIZ_TOKEN'))
            ->asForm()
            ->post('https://notify.eskiz.uz/api/message/sms/send', [
                'mobile_phone' => '998' . $request->phone,
                'message' => "Bu Eskiz dan test",
                'from' => '4546',
            ]);

        if ($response->failed()) {
            $user->delete();
            Cache::forget('pending_user_' . $verificationId);
            Cache::forget('pending_user_' . $verificationId . '_attempts');
            return response()->json(['message' => 'Не удалось отправить SMS, попробуйте снова'], 500);
        }

        return response()->json([
            'message' => 'Профиль создан. На ваш номер отправлено SMS.',
            'verification_id' => $verificationId
        ]);
    }


    public function verifySmsAndActivate(Request $request)
    {
        $request->validate([
            'verification_id' => 'required|uuid',
            'message' => 'required|string',
        ]);

        $key = 'pending_user_' . $request->verification_id;
        $attemptsKey = $key . '_attempts';

        $userId = Cache::get($key);

        if (!$userId) {
            return response()->json(['message' => 'Срок подтверждения истёк или профиль не найден'], 422);
        }

        $user = User::find($userId);

        if (!$user) {
            Cache::forget($key);
            Cache::forget($attemptsKey);
            return response()->json(['message' => 'Профиль не найден'], 422);
        }

        if ($request->message !== 'Bu Eskiz dan test') {
            $attempts = Cache::increment($attemptsKey);

            if ($attempts >= 3) {
                $user->delete();
                Cache::forget($key);
                Cache::forget($attemptsKey);
                return response()->json(['message' => 'Превышено количество попыток. Попробуйте снова зарегистрироваться.'], 422);
            }

            return response()->json(['message' => 'Неверный текст подтверждения. Попробуйте снова'], 422);
        }

        // Успешная активация
        $token = $user->createToken('auth_token')->plainTextToken;

        Cache::forget($key);
        Cache::forget($attemptsKey);

        return response()->json([
            'message' => 'Регистрация прошла успешно',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

}

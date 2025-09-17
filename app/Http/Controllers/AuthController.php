<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{

    // 📌 Регистрация
    public function register1(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|size:12|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'phone.unique' => 'Вы раньше зарегистрировались с этим номером',
        ]);

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'is_verified' => false, // ✨ не подтвержден
        ]);

        // ссылка на бота
        $botLink = "https://t.me/BirgaYul_bot?start=" . $user->phone;

        return response()->json([
            'message' => 'Регистрация прошла успешно. Подтвердите телефон в Telegram.',
            'telegram_link' => $botLink,
            'user' => $user,
        ], 201);
    }

    // 📌 Подтверждение кода
    public function verifyCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|size:12',
            'code' => 'required|string|size:4',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        if ($user->verification_code === $request->code) {
            $user->is_verified = true;
            $user->verification_code = null;
            $user->save();

            // выдаем токен
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Телефон подтвержден ✅',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ]);
        }

        return response()->json(['error' => 'Неверный код'], 400);
    }

    // 📌 Telegram webhook
    public function telegramWebhook(Request $request)
    {
        $update = $request->all();
        \Log::info('Telegram webhook', $update); // логируем всё, что пришло

        $botToken = env('TELEGRAM_BOT_TOKEN');

        if (isset($update['message']['text'])) {
            $text = $update['message']['text'];
            $chatId = $update['message']['chat']['id'];

            if (str_starts_with($text, "/start")) {
                $phone = trim(str_replace("/start", "", $text));
                $phone = ltrim($phone);

                $user = User::where('phone', $phone)->first();
                if ($user) {
                    $code = rand(1000, 9999);
                    $user->verification_code = $code;
                    $user->telegram_chat_id = $chatId;
                    $user->save();

                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => "Ваш код подтверждения: {$code}",
                    ]);
                } else {
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => "Номер не найден. Сначала зарегистрируйтесь на сайте.",
                    ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }


    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|size:9|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'phone.unique' => 'Вы раньше зарегистрировались с этим номером',
        ]);

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Автоматическая выдача токена после успешной регистрации
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Регистрация прошла успешно',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }


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

    public function resetPassword(Request $request)
    {
        $request->validate([
            'phone'    => 'required|size:9|exists:users,phone',
            'password' => 'required|min:6|confirmed',
        ], [
            'phone.required'     => 'Номер телефона обязателен',
            'phone.size'         => 'Номер телефона должен состоять из 9 цифр',
            'phone.exists'       => 'Пользователь с таким номером не найден',
            'password.required'  => 'Пароль обязателен',
            'password.min'       => 'Пароль должен содержать минимум 6 символов',
            'password.confirmed' => 'Пароли не совпадают',
        ]);

        $user = User::where('phone', $request->phone)->first();

        $user->password = Hash::make($request->password);
        $user->save();

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

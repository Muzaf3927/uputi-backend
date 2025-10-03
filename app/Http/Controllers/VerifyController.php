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
            'phone' => 'required|string|size:9',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $existing = User::where('phone', $request->phone)->exists();
        if ($existing) {
            return response()->json(['message' => 'You have already registered with this number'], 422);
        }

        // Генерация кода и verification_id
        $code = rand(100000, 999999);
        $verificationId = (string) Str::uuid();
        $ttl = now()->addSeconds(180); // или: $ttl = 30;

        // Сохраняем временные данные в кэш
        Cache::put("pending_user_{$verificationId}", [
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ], $ttl);

        Cache::put("pending_user_{$verificationId}_code", $code, $ttl);
        Cache::put("pending_user_{$verificationId}_attempts", 0, $ttl);

        // Отправка SMS
        $response = Http::withBasicAuth('Uputi@2025', 'uputi@2dfS')
            ->acceptJson()
            ->post('https://api.telecom-qqm-it.uz/api/v1/agent/sms/send', [
                'to' => '998' . $request->phone,
                'senderId' => '2702',
                'merchantId' => 'MCHUPUTI',
                'message' => "Vash kod dla vxoda v prilojenii UPuti: $code",
                'messageId' => $verificationId,
            ]);

        if ($response->failed()) {
            Cache::forget("pending_user_{$verificationId}");
            Cache::forget("pending_user_{$verificationId}_code");
            Cache::forget("pending_user_{$verificationId}_attempts");

            return response()->json(['message' => 'Failed to send SMS, please try again'], 500);
        }

        return response()->json([
            'message' => 'Verification code sent to your phone.',
            'verification_id' => $verificationId
        ]);
    }




    public function verifySmsAndActivate(Request $request)
    {
        $request->validate([
            'verification_id' => 'required|uuid',
            'code' => 'required|digits:6',
        ]);

        $key = 'pending_user_' . $request->verification_id;
        $codeKey = $key . '_code';
        $attemptsKey = $key . '_attempts';

        $userData = Cache::get($key);
        $cachedCode = Cache::get($codeKey);

        if (!$userData || !$cachedCode) {
            return response()->json(['message' => 'Verification expired or not found.'], 422);
        }

        if ((string) $request->code !== (string) $cachedCode) {
            $attempts = Cache::increment($attemptsKey);
            if ($attempts >= 3) {
                Cache::forget($key);
                Cache::forget($codeKey);
                Cache::forget($attemptsKey);
                return response()->json(['message' => 'Too many attempts. Try registering again.'], 422);
            }
            return response()->json(['message' => 'Invalid code.'], 422);
        }

        // Перед созданием — на всякий случай ещё раз проверить, что номер не занят
        $exists = User::where('phone', $userData['phone'])->exists();
        if ($exists) {
            Cache::forget($key);
            Cache::forget($codeKey);
            Cache::forget($attemptsKey);
            return response()->json(['message' => 'Phone already registered.'], 422);
        }

        // Всё ок — создаём пользователя
        $user = User::create($userData);
        $token = $user->createToken('auth_token')->plainTextToken;

        Cache::forget($key);
        Cache::forget($codeKey);
        Cache::forget($attemptsKey);

        return response()->json([
            'message' => 'Registration successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

}

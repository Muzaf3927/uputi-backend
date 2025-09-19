<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'phone'    => 'required|size:9',
            'password' => 'required',
        ], [
            'phone.required' => 'Phone number is required',
            'phone.size'     => 'Phone number must be 9 digits',
            'password.required' => 'Password is required',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid login or password'], 401);
        }

        // Удаляем старые токены
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Login successful',
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
            'phone.required' => 'Phone number is required',
            'phone.size'     => 'Phone number must be 9 digits',
            'phone.exists'   => 'User with this phone number not found',
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
            return response()->json(['message' => 'Failed to send SMS, please try again'], 500);
        }

        return response()->json([
            'message' => 'SMS sent to your number for confirmation',
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
            'password.required'  => 'Password is required',
            'password.min'       => 'Password must be at least 6 characters',
            'password.confirmed' => 'Passwords do not match',
        ]);

        $key = 'reset_user_' . $request->verification_id;
        $attemptsKey = $key . '_attempts';

        $userId = Cache::get($key);

        if (!$userId) {
            return response()->json(['message' => 'Confirmation period expired or request not found'], 422);
        }

        $user = User::find($userId);
        if (!$user) {
            Cache::forget($key);
            Cache::forget($attemptsKey);
            return response()->json(['message' => 'User not found'], 422);
        }

        // Проверка тестового текста
        if ($request->message !== 'Parolni tiklash uchun kod: 123456') {
            $attempts = Cache::increment($attemptsKey);

            if ($attempts >= 3) {
                Cache::forget($key);
                Cache::forget($attemptsKey);
                return response()->json(['message' => 'Maximum attempts exceeded. Please try again'], 422);
            }

            return response()->json(['message' => 'Invalid confirmation code. Please try again'], 422);
        }

        // Всё ок → обновляем пароль
        $user->password = Hash::make($request->password);
        $user->save();

        Cache::forget($key);
        Cache::forget($attemptsKey);

        return response()->json([
            'message' => 'Password updated successfully',
            'success' => true,
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'You have logged out']);
    }
}

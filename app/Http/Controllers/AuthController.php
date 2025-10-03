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
        ]);

        $user = User::where('phone', $request->phone)->first();

        $code = rand(100000, 999999);
        $verificationId = (string) Str::uuid();
        $ttl = now()->addSeconds(180);

        Cache::put('reset_user_' . $verificationId, $user->id, $ttl);
        Cache::put('reset_user_' . $verificationId . '_code', $code, $ttl);
        Cache::put('reset_user_' . $verificationId . '_attempts', 0, $ttl);

        $response = Http::withBasicAuth('Uputi@2025', 'uputi@2dfS')
            ->acceptJson()
            ->post('https://api.telecom-qqm-it.uz/api/v1/agent/sms/send', [
                'to' => '998' . $request->phone,
                'senderId' => '2702',
                'merchantId' => 'MCHUPUTI',
                'message' => "Vash kod dlya vosstanovleniya parolya UPuti: $code",
                'messageId' => $verificationId,
            ]);

        if ($response->failed()) {
            Cache::forget('reset_user_' . $verificationId);
            Cache::forget('reset_user_' . $verificationId . '_code');
            Cache::forget('reset_user_' . $verificationId . '_attempts');
            return response()->json(['message' => 'Failed to send SMS, please try again'], 500);
        }

        return response()->json([
            'message' => 'Verification code sent to your phone.',
            'verification_id' => $verificationId,
        ]);
    }


// 2-й шаг: проверяем код и меняем пароль
    public function resetPasswordStepTwo(Request $request)
    {
        $request->validate([
            'verification_id' => 'required|uuid',
            'code' => 'required|digits:6',
            'password' => 'required|min:6|confirmed',
        ]);

        $key = 'reset_user_' . $request->verification_id;
        $codeKey = $key . '_code';
        $attemptsKey = $key . '_attempts';

        $userId = Cache::get($key);
        $cachedCode = Cache::get($codeKey);

        if (!$userId || !$cachedCode) {
            return response()->json(['message' => 'Confirmation period expired or request not found'], 422);
        }

        $user = User::find($userId);
        if (!$user) {
            Cache::forget($key);
            Cache::forget($codeKey);
            Cache::forget($attemptsKey);
            return response()->json(['message' => 'User not found'], 422);
        }

        if ((string) $request->code !== (string) $cachedCode) {
            $attempts = Cache::increment($attemptsKey);

            if ($attempts >= 3) {
                Cache::forget($key);
                Cache::forget($codeKey);
                Cache::forget($attemptsKey);
                return response()->json(['message' => 'Maximum attempts exceeded. Please try again'], 422);
            }

            return response()->json(['message' => 'Invalid confirmation code. Please try again'], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        Cache::forget($key);
        Cache::forget($codeKey);
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

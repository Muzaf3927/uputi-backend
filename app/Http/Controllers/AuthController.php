<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    // === STEP 1: SEND OTP ===
    public function start(Request $request)
    {
        $request->validate([
            'phone' => 'required|size:9',
            'name'  => 'required|string|max:30', // имя нужно только при регистрации
        ]);

        // ===== DEV LOGIN WITHOUT OTP =====
        if ($request->phone === '123123123' || $request->phone === '910018902') {

            $user = User::where('phone', $request->phone)->first();

            // удаляем старые токены
            $user->tokens()->delete();

            // создаём новый токен
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Dev login (no OTP)',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ]);
        }

        $phone = $request->phone;

        $existingUser = User::where('phone', $phone)->first();

        $code = rand(100000, 999999);
        $verificationId = (string) Str::uuid();
        $ttl = now()->addSeconds(180);

        // если юзер есть — сохраняем его id
        if ($existingUser) {
            Cache::put("auth_{$verificationId}", [
                'type' => 'login',
                'user_id' => $existingUser->id,
            ], $ttl);
        } else {
            // если юзера нет — сохраняем данные для создания
            Cache::put("auth_{$verificationId}", [
                'type' => 'register',
                'name' => $request->name,
                'phone' => $phone,
            ], $ttl);
        }

        Cache::put("auth_{$verificationId}_code", $code, $ttl);
        Cache::put("auth_{$verificationId}_attempts", 0, $ttl);

        // отправка SMS
        $response = Http::withBasicAuth('Uputi@2025', 'uputi@2dfS')
            ->acceptJson()
            ->post('https://api.telecom-qqm-it.uz/api/v1/agent/sms/send', [
                'to' => '998' . $phone,
                'senderId' => '2702',
                'merchantId' => 'MCHUPUTI',
                'message' => "Vash kod dlya vxoda v UPuti: $code",
                'messageId' => $verificationId,
            ]);

        if ($response->failed()) {
            Cache::forget("auth_{$verificationId}");
            Cache::forget("auth_{$verificationId}_code");
            Cache::forget("auth_{$verificationId}_attempts");

            return response()->json(['message' => 'Failed to send SMS'], 500);
        }

        return response()->json([
            'message' => 'Code sent',
            'verification_id' => $verificationId,
        ]);
    }

    // === STEP 2: VERIFY CODE ===
    public function verify(Request $request)
    {
        $request->validate([
            'verification_id' => 'required|uuid',
            'code' => 'required|digits:6',
        ]);

        $key = "auth_{$request->verification_id}";
        $data = Cache::get($key);
        $cachedCode = Cache::get("{$key}_code");

        if (!$data || !$cachedCode) {
            return response()->json(['message' => 'Verification expired'], 422);
        }

        // Код неправильный
        if ((string) $request->code !== (string) $cachedCode) {
            $attempts = Cache::increment("{$key}_attempts");

            if ($attempts >= 3) {
                Cache::forget($key);
                Cache::forget("{$key}_code");
                Cache::forget("{$key}_attempts");
                return response()->json(['message' => 'Too many attempts'], 422);
            }

            return response()->json(['message' => 'Invalid code'], 422);
        }

        // --- LOGIN ---
        if ($data['type'] === 'login') {
            $user = User::find($data['user_id']);

            // удаляем старые токены
            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;
        }

        // --- REGISTER ---
        if ($data['type'] === 'register') {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => null,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;
        }

        // очищаем кэш
        Cache::forget($key);
        Cache::forget("{$key}_code");
        Cache::forget("{$key}_attempts");

        return response()->json([
            'message' => 'Successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\User;

class AccountDeletionController extends Controller
{
    // === STEP 1: SEND OTP FOR DELETION ===
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|size:9',
        ]);

        if (in_array($request->phone, ['123123123'])) {
            return response()->json(['message' => 'Account deleted successfully']);
        }

        $user = User::where('phone', $request->phone)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $code = rand(100000, 999999);
        $verificationId = (string) Str::uuid();
        $ttl = now()->addSeconds(180);

        Cache::put("delete_{$verificationId}", [
            'user_id' => $user->id,
        ], $ttl);

        Cache::put("delete_{$verificationId}_code", $code, $ttl);
        Cache::put("delete_{$verificationId}_attempts", 0, $ttl);

        // отправка SMS
        $response = Http::withBasicAuth('Uputi@2025', 'uputi@2dfS')
            ->acceptJson()
            ->post('https://api.telecom-qqm-it.uz/api/v1/agent/sms/send', [
                'to' => '998' . $request->phone,
                'senderId' => '2702',
                'merchantId' => 'MCHUPUTI',
                'message' => "Vash kod dlya vxoda v UPuti:: $code",
                'messageId' => $verificationId,
            ]);

        if ($response->failed()) {
            Cache::forget("delete_{$verificationId}");
            Cache::forget("delete_{$verificationId}_code");
            Cache::forget("delete_{$verificationId}_attempts");

            return response()->json(['message' => 'Failed to send SMS'], 500);
        }

        return response()->json([
            'message' => 'OTP sent to your phone',
            'verification_id' => $verificationId,
        ]);
    }

    // === STEP 2: VERIFY OTP AND DELETE ACCOUNT ===
    public function verifyAndDelete(Request $request)
    {
        $request->validate([
            'verification_id' => 'required|uuid',
            'code' => 'required|digits:6',
        ]);

        $key = "delete_{$request->verification_id}";
        $data = Cache::get($key);
        $cachedCode = Cache::get("{$key}_code");

        if (!$data || !$cachedCode) {
            return response()->json(['message' => 'Verification expired'], 422);
        }

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

        $user = User::find($data['user_id']);
        if ($user) {
            $user->tokens()->delete();
            $user->delete();
        }

        Cache::forget($key);
        Cache::forget("{$key}_code");
        Cache::forget("{$key}_attempts");

        return response()->json(['message' => 'Account deleted successfully']);
    }
}



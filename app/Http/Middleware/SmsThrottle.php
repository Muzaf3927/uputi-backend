<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class SmsThrottle
{
    public function handle($request, Closure $next)
    {
        $phone = $request->input('phone');
        $ip    = $request->ip();
        if($phone == 123123123 || $phone == 910018902){
            return $next($request);
        }

        if (!$phone) {
            return response()->json(['message' => 'Phone number required'], 422);
        }

        // Ключи для лимитов
        $perMinuteKey = "sms_last_sent_{$phone}";
        $dailyPhoneKey = "sms_daily_count_{$phone}";
        $dailyIpKey = "sms_daily_count_ip_{$ip}";

        // 1. Проверка: не чаще чем раз в 60 сек
        if (Cache::has($perMinuteKey)) {
            $secondsLeft = Cache::get($perMinuteKey) - time();
            return response()->json([
                'message' => "Too many requests. Try again after {$secondsLeft} seconds."
            ], 429);
        }

        // 2. Проверка: не больше 5 SMS в сутки на номер
        $dailyPhoneCount = Cache::get($dailyPhoneKey, 0);
        if ($dailyPhoneCount >= 5) {
            return response()->json([
                'message' => 'Daily SMS limit reached for this number'
            ], 429);
        }

        // 3. Проверка: не больше 20 SMS в сутки с одного IP
        $dailyIpCount = Cache::get($dailyIpKey, 0);
        if ($dailyIpCount >= 20
        ) {
            return response()->json([
                'message' => 'Daily SMS limit reached for your IP address'
            ], 429);
        }

        // Обновляем лимиты
        Cache::put($perMinuteKey, time() + 60, now()->addSeconds(60)); // блокировка на 60 сек
        Cache::put($dailyPhoneKey, $dailyPhoneCount + 1, now()->endOfDay()); // +1 к счётчику номера
        Cache::put($dailyIpKey, $dailyIpCount + 1, now()->endOfDay());       // +1 к счётчику IP

        return $next($request);
    }
}

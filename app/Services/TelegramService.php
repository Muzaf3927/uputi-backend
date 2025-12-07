<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    public function sendMessage($chatId, $text)
    {
        $token = config('services.telegram.bot_token');

        return Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);
    }
}

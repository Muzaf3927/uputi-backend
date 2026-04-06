<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $token;
    protected string $baseUrl;

    public function __construct(string $token = null)
    {
        $this->token = $token ?? config('services.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function sendMessage($chatId, $text, array $replyMarkup = [])
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if (!empty($replyMarkup)) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return Http::post("{$this->baseUrl}/sendMessage", $params);
    }

    public function requestContact($chatId, $text)
    {
        return $this->sendMessage($chatId, $text, [
            'keyboard' => [
                [['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }

    public function removeKeyboard($chatId, $text)
    {
        return $this->sendMessage($chatId, $text, [
            'remove_keyboard' => true,
        ]);
    }
}

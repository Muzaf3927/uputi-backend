<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chatId;
    public $text;

    // 🔥 Максимум попыток
    public $tries = 3;

    // 🔥 Задержка между повторными попытками (в секундах)
    public $backoff = 5;

    // 🔥 Таймаут выполнения job (в секундах)
    public $timeout = 30;

    public function __construct($chatId, $text)
    {
        $this->chatId = $chatId;
        $this->text = $text;
    }

    public function handle(TelegramService $telegram)
    {
        // 🔥 Маленькая пауза для снижения rate limit Telegram
        usleep(200000); // 0.2 секунды

        $telegram->sendMessage($this->chatId, $this->text);
    }
}

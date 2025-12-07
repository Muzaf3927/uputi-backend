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

    public function __construct($chatId, $text)
    {
        $this->chatId = $chatId;
        $this->text = $text;
    }

    public function handle(TelegramService $telegram)
    {
        $telegram->sendMessage($this->chatId, $this->text);
    }
}

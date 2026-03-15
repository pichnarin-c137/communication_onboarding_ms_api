<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
    }

    /**
     * Send a message directly to a Telegram chat by chat_id.
     *
     * This is used for synchronous, unlogged sends (e.g. webhook confirmation messages).
     * For all standard group notifications, use TelegramGroupService::sendMessage() which
     * dispatches the queued SendTelegramNotification job.
     */
    public function sendToChat(string $chatId, string $text): bool
    {
        if (! $chatId || ! $this->botToken) {
            return false;
        }

        try {
            $response = Http::post(
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text'    => $text,
                ]
            );

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('TelegramService: failed to send direct message', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Return the configured bot token (useful for external HTTP calls).
     */
    public function getBotToken(): string
    {
        return $this->botToken;
    }
}

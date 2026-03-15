<?php

namespace App\Jobs;

use App\Models\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted before failing.
     */
    public int $tries;

    public function __construct(
        public TelegramMessage $telegramMessage,
    ) {
        $this->tries = config('coms.telegram_message_retry_limit', 3);
    }

    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token', '');

        if (! $botToken) {
            Log::warning('SendTelegramNotification: TELEGRAM_BOT_TOKEN is not configured.', [
                'telegram_message_id' => $this->telegramMessage->id,
            ]);

            $this->telegramMessage->update([
                'status'        => 'failed',
                'error_message' => 'Bot token is not configured.',
            ]);

            return;
        }

        $group = $this->telegramMessage->telegramGroup;

        if (! $group || ! $group->chat_id) {
            $this->telegramMessage->update([
                'status'        => 'failed',
                'error_message' => 'Telegram group or chat_id not found.',
            ]);

            return;
        }

        $response = Http::post(
            "https://api.telegram.org/bot{$botToken}/sendMessage",
            [
                'chat_id' => $group->chat_id,
                'text'    => $this->telegramMessage->message_body,
            ]
        );

        if ($response->successful()) {
            $this->telegramMessage->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
        } else {
            $errorBody = $response->body();

            Log::warning('SendTelegramNotification: Telegram API returned an error.', [
                'telegram_message_id' => $this->telegramMessage->id,
                'status_code'         => $response->status(),
                'response_body'       => $errorBody,
            ]);

            // Re-throw to trigger retry logic via the queue
            throw new \RuntimeException("Telegram API error: {$errorBody}");
        }
    }

    /**
     * Handle job failure after all retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        try {
            $this->telegramMessage->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        } catch (\Throwable $updateException) {
            Log::error('SendTelegramNotification: failed to update TelegramMessage status on failure.', [
                'telegram_message_id' => $this->telegramMessage->id,
                'original_error'      => $e->getMessage(),
                'update_error'        => $updateException->getMessage(),
            ]);
        }
    }
}

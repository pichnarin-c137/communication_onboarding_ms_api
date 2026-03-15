<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:set-webhook';

    /**
     * The console command description.
     */
    protected $description = 'Register the Telegram webhook URL with the Telegram Bot API.';

    public function handle(): int
    {
        $botToken   = config('services.telegram.bot_token', '');
        $webhookUrl = env('TELEGRAM_WEBHOOK_URL', '');

        if (! $botToken) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured. Please set it in your .env file.');

            return self::FAILURE;
        }

        if (! $webhookUrl) {
            $this->error('TELEGRAM_WEBHOOK_URL is not configured. Please set it in your .env file.');

            return self::FAILURE;
        }

        $secretToken = config('coms.telegram_webhook_secret', '');

        $this->info("Registering webhook URL: {$webhookUrl}");

        $response = Http::post(
            "https://api.telegram.org/bot{$botToken}/setWebhook",
            array_filter([
                'url'          => $webhookUrl,
                'secret_token' => $secretToken ?: null,
            ])
        );

        $body = $response->json();

        if ($response->successful() && ($body['ok'] ?? false)) {
            $this->info("Webhook registered successfully: {$webhookUrl}");

            return self::SUCCESS;
        }

        $description = $body['description'] ?? $response->body();
        $this->error("Failed to register webhook: {$description}");

        return self::FAILURE;
    }
}

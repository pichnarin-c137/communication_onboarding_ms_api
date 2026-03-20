<?php

namespace App\Services\Telegram;

use App\Exceptions\Business\TelegramSetupException;
use App\Models\TelegramEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookService
{
    public function __construct(
        private TelegramGroupService $groupService,
    ) {}

    /**
     * Entry point for all incoming Telegram webhook payloads.
     * Always resolves without throwing — Telegram requires a 200 response.
     */
    public function handle(array $payload): void
    {
        // Determine chat_id for logging
        $chatId = $this->extractChatId($payload) ?? 'unknown';

        // Detect and classify the event type before processing
        $eventType = $this->detectEventType($payload);

        // Log the raw event first — if processing fails, the raw event is still recorded
        $this->logEvent($chatId, $eventType, $payload);

        match ($eventType) {
            'setup_command' => $this->handleSetupCommand($payload),
            'bot_removed'   => $this->handleBotRemoved($payload),
            default         => null, // unknown events are already logged — nothing else to do
        };
    }

    // Event handlers

    private function handleSetupCommand(array $payload): void
    {
        try {
            $text      = $payload['message']['text'] ?? '';
            preg_match('/^\/setup(?:@\w+)?\s+(\S+)/', $text, $matches);
            $token     = $matches[1] ?? '';
            $chatId    = (string) ($payload['message']['chat']['id'] ?? '');
            $groupName = $payload['message']['chat']['title'] ?? 'Unknown Group';

            $this->groupService->registerGroup($token, $chatId, $groupName);

            // Send direct confirmation back (not queued, not logged as a TelegramMessage)
            $this->sendDirectMessage($chatId, "This group has been successfully connected to COMS.");
        } catch (TelegramSetupException $e) {
            $chatId = (string) ($payload['message']['chat']['id'] ?? '');
            $this->sendDirectMessage($chatId, "Setup failed: {$e->getMessage()}");
        } catch (\Throwable $e) {
            Log::error('TelegramWebhookService: unexpected error in handleSetupCommand', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleBotRemoved(array $payload): void
    {
        try {
            $chatId = (string) ($payload['my_chat_member']['chat']['id'] ?? '');
            $this->groupService->markBotRemoved($chatId);
        } catch (\Throwable $e) {
            Log::error('TelegramWebhookService: unexpected error in handleBotRemoved', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Private helpers

    private function detectEventType(array $payload): string
    {
        $messageText = $payload['message']['text'] ?? '';

        // Match /setup TOKEN and /setup@botname TOKEN (Telegram appends @botname in groups)
        if (preg_match('/^\/setup(?:@\w+)?\s+(\S+)/', $messageText)) {
            return 'setup_command';
        }

        if (isset($payload['my_chat_member'])) {
            $newStatus = $payload['my_chat_member']['new_chat_member']['status'] ?? '';
            if (in_array($newStatus, ['left', 'kicked'], true)) {
                return 'bot_removed';
            }
        }

        return 'unknown';
    }

    private function extractChatId(array $payload): ?string
    {
        if (isset($payload['message']['chat']['id'])) {
            return (string) $payload['message']['chat']['id'];
        }

        if (isset($payload['my_chat_member']['chat']['id'])) {
            return (string) $payload['my_chat_member']['chat']['id'];
        }

        return null;
    }

    /**
     * Log a raw Telegram webhook event to the telegram_events table.
     * Wrapped in try/catch — logging must never break the main flow.
     */
    private function logEvent(string $chatId, string $eventType, array $payload): void
    {
        try {
            TelegramEvent::create([
                'chat_id'    => $chatId,
                'event_type' => $eventType,
                'payload'    => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('TelegramWebhookService: failed to log telegram event', [
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a direct (synchronous, unlogged) message back to Telegram.
     * Used only for immediate webhook responses like setup confirmation/error.
     */
    private function sendDirectMessage(string $chatId, string $text): void
    {
        try {
            $botToken = config('services.telegram.bot_token', '');

            if (! $botToken || ! $chatId) {
                return;
            }

            Http::post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text'    => $text,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('TelegramWebhookService: failed to send direct message', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}

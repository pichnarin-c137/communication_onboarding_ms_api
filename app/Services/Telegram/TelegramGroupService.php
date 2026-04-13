<?php

namespace App\Services\Telegram;

use App\Exceptions\Business\TelegramSetupException;
use App\Models\TelegramGroup;
use App\Models\TelegramMessage;
use App\Models\TelegramSetupToken;
use App\Services\Notification\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramGroupService
{
    public function __construct(
        private TelegramService $telegramService,
        private TelegramMessageTemplate $template,
        private \App\Services\UserSettingsService $userSettingsService,
    ) {}

    // Token management

    /**
     * Generate a new setup token for a client. Invalidates any existing active tokens.
     */
    public function generateToken(string $clientId, string $createdBy): TelegramSetupToken
    {
        // Invalidate existing unused, unexpired tokens for this client
        TelegramSetupToken::where('client_id', $clientId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        return TelegramSetupToken::create([
            'client_id'  => $clientId,
            'created_by' => $createdBy,
            'token'      => Str::random(32),
            'expires_at' => now()->addSeconds((int) config('coms.telegram_setup_token_ttl', 3600)),
        ]);
    }

    // Group registration

    /**
     * Register a Telegram group via a setup token sent from inside the group.
     */
    public function registerGroup(string $token, string $chatId, string $groupName): TelegramGroup
    {
        $tokenRecord = TelegramSetupToken::where('token', $token)->first();

        if (! $tokenRecord) {
            throw new TelegramSetupException('Token not found', 404);
        }

        if ($tokenRecord->isExpired()) {
            throw new TelegramSetupException('Token has expired', 422);
        }

        if ($tokenRecord->isUsed()) {
            throw new TelegramSetupException('Token has already been used', 422);
        }

        // Block re-registration only if an active (non-deleted) group already uses this chat_id
        $existingGroup = TelegramGroup::where('chat_id', $chatId)->first();

        if ($existingGroup) {
            throw new TelegramSetupException('This Telegram group is already connected', 422);
        }

        // Hard-delete any soft-deleted record for this chat_id so the new create doesn't hit
        // a unique constraint violation on chat_id
        TelegramGroup::withTrashed()->where('chat_id', $chatId)->forceDelete();

        $tokenRecord->update(['used_at' => now()]);

        return TelegramGroup::create([
            'client_id'    => $tokenRecord->client_id,
            'chat_id'      => $chatId,
            'group_name'   => $groupName,
            'bot_status'   => 'connected',
            'language'     => config('coms.telegram_default_language', 'en'),
            'connected_by' => $tokenRecord->created_by,
            'connected_at' => now(),
        ]);
    }

    // Group management

    /**
     * Manually disconnect and reconnect a group from COMS.
     */
    public function disconnectGroup(string $groupId): TelegramGroup
    {
        $group = TelegramGroup::findOrFail($groupId);

        $group->update([
            'bot_status'       => 'removed',
            'disconnected_at'  => now(),
        ]);

        return $group->fresh();
    }

    public function reconnectGroup(string $groupId): TelegramGroup
    {
        $group = TelegramGroup::findOrFail($groupId);

        $group->update([
            'bot_status'       => 'reconnected',
            'reconnected_at'  => now(),
        ]);

        return $group->fresh();
    }

    /**
     * Update the preferred language for a group.
     */
    public function updateLanguage(string $groupId, string $language): TelegramGroup
    {
        $supported = config('coms.telegram_supported_languages', ['en', 'km']);

        if (! in_array($language, $supported, true)) {
            throw new \InvalidArgumentException(
                "Language '{$language}' is not supported. Supported: " . implode(', ', $supported)
            );
        }

        $group = TelegramGroup::findOrFail($groupId);
        $group->update(['language' => $language]);

        return $group->fresh();
    }

    /**
     * Mark a group's bot as removed when Telegram sends a bot_removed event.
     */
    public function markBotRemoved(string $chatId): void
    {
        $group = TelegramGroup::where('chat_id', $chatId)
            ->where('bot_status', 'connected')
            ->first();

        if (! $group) {
            // Bot was removed from an unregistered or already-disconnected group — silently ignore
            return;
        }

        $group->update([
            'bot_status'      => 'removed',
            'disconnected_at' => now(),
        ]);
    }

    // Messaging

    /**
     * Render a message and dispatch the queued send job.
     * Creates a TelegramMessage record with status 'pending' before dispatching.
     */
    public function sendMessage(TelegramGroup $group, string $messageType, array $variables = []): void
    {
        $messageBody = $this->template->render($messageType, $group->language, $variables);

        $telegramMessage = TelegramMessage::create([
            'telegram_group_id' => $group->id,
            'message_type'      => $messageType,
            'message_body'      => $messageBody,
            'language'          => $group->language,
            'status'            => 'pending',
        ]);

        \App\Jobs\SendTelegramNotification::dispatch($telegramMessage);
    }

    /**
     * Send a test message to verify the bot connection.
     */
    public function sendTestMessage(string $groupId): void
    {
        $group = TelegramGroup::findOrFail($groupId);

        if (! in_array($group->bot_status, ['connected', 'reconnected'], true)) {
            throw new TelegramSetupException('Bot is not connected to this group', 422);
        }

        $clientName = $group->client->company_name ?? 'Client';

        $this->sendMessage($group, 'test_message', ['client_name' => $clientName]);
    }

    // Internal helpers

    /**
     * Find the connected TelegramGroup for a given client_id, if any.
     * Returns null silently if no connected group exists.
     */
    public function findConnectedGroupForClient(string $clientId): ?TelegramGroup
    {
        return TelegramGroup::connected()
            ->forClient($clientId)
            ->first();
    }

    /**
     * Attempt to send a Telegram message for a client.
     * If no connected group exists, silently returns.
     * Failures are caught and logged — they must never propagate.
     */
    public function notifyClient(string $clientId, string $messageType, array $variables = [], ?string $actingUserId = null): void
    {
        try {
            if ($actingUserId && ! $this->userSettingsService->shouldDeliver($actingUserId, 'telegram')) {
                return;
            }

            $group = $this->findConnectedGroupForClient($clientId);

            if (! $group) {
                return;
            }

            $this->sendMessage($group, $messageType, $variables);
        } catch (\Throwable $e) {
            Log::error('TelegramGroupService: failed to notify client', [
                'client_id'    => $clientId,
                'message_type' => $messageType,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Services\Notification;

use App\Models\ClientContact;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    private string $botToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
    }

    public function sendToContact(ClientContact $contact, string $messageType, array $context): void
    {
        if (! $contact->telegram_chat_id || ! $this->botToken) {
            return;
        }

        $text = $this->buildMessage($messageType, $context);

        $telegramMessage = TelegramMessage::create([
            'client_contact_id' => $contact->id,
            'message_type' => $messageType,
            'message_content' => $text,
            'delivery_status' => 'pending',
            'related_session_id' => $context['session']['id'] ?? null,
        ]);

        $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'chat_id' => $contact->telegram_chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $telegramMessage->update([
                'telegram_message_id' => $data['result']['message_id'] ?? null,
                'sent_at' => now(),
                'delivery_status' => 'sent',
            ]);
        } else {
            $telegramMessage->update([
                'delivery_status' => 'failed',
                'error_message' => $response->body(),
            ]);
        }
    }

    public function updateDeliveryStatus(string $messageId, string $status, ?string $error = null): void
    {
        $update = ['delivery_status' => $status];

        if ($error !== null) {
            $update['error_message'] = $error;
        }

        TelegramMessage::where('id', $messageId)->update($update);
    }

    private function buildMessage(string $messageType, array $context): string
    {
        return match ($messageType) {
            'session_scheduled' => $this->buildSessionScheduledMessage($context),
            'session_rescheduled' => $this->buildRescheduledMessage($context),
            'session_cancelled' => $this->buildCancelledMessage($context),
            'session_reminder' => $this->buildReminderMessage($context),
            default => 'You have a session notification.',
        };
    }

    private function buildSessionScheduledMessage(array $context): string
    {
        $session = $context['session'] ?? [];
        $title = $session['session_title'] ?? 'Training Session';
        $date = $session['scheduled_date'] ?? '';
        $start = $session['scheduled_start_time'] ?? '';
        $end = $session['scheduled_end_time'] ?? '';

        return "<b>Session Scheduled</b>\n\n"
            ."📅 <b>{$title}</b>\n"
            ."Date: {$date}\n"
            ."Time: {$start} – {$end}";
    }

    private function buildRescheduledMessage(array $context): string
    {
        $session = $context['session'] ?? [];
        $title = $session['session_title'] ?? 'Training Session';
        $date = $session['scheduled_date'] ?? '';
        $start = $session['scheduled_start_time'] ?? '';

        return "<b>Session Rescheduled</b>\n\n"
            ."📅 <b>{$title}</b> has been rescheduled.\n"
            ."New Date: {$date} at {$start}";
    }

    private function buildCancelledMessage(array $context): string
    {
        $session = $context['session'] ?? [];
        $title = $session['session_title'] ?? 'Training Session';

        return "<b>Session Cancelled</b>\n\n"
            ."❌ <b>{$title}</b> has been cancelled.";
    }

    private function buildReminderMessage(array $context): string
    {
        $session = $context['session'] ?? [];
        $title = $session['session_title'] ?? 'Training Session';
        $date = $session['scheduled_date'] ?? '';
        $start = $session['scheduled_start_time'] ?? '';

        return "<b>Session Reminder</b>\n\n"
            ."⏰ Reminder: <b>{$title}</b>\n"
            ."Date: {$date} at {$start}";
    }
}

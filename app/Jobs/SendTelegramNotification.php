<?php

namespace App\Jobs;

use App\Models\ClientContact;
use App\Services\Notification\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ClientContact $contact,
        public string $messageType,
        public array $context,
        public string $sessionId
    ) {}

    public function handle(TelegramService $service): void
    {
        $service->sendToContact($this->contact, $this->messageType, $this->context);
    }

    public function failed(\Throwable $e): void
    {
        \App\Models\TelegramMessage::where('related_session_id', $this->sessionId)
            ->where('client_contact_id', $this->contact->id)
            ->where('delivery_status', 'pending')
            ->update([
                'delivery_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\PlaylistVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendPlaylistVideoToTelegram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly PlaylistVideo $video,
        public readonly string $chatId,
        public readonly ?string $message = null,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token', '');

        if (! $botToken) {
            Log::warning('SendPlaylistVideoToTelegram: bot token not configured.', [
                'video_id' => $this->video->id,
            ]);

            return;
        }

        $text = "<b>{$this->video->title}</b>\n{$this->video->youtube_url}";

        if ($this->message) {
            $text .= "\n\n$this->message";
        }

        $response = Http::post("https://api.telegram.org/bot$botToken/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Telegram API error: '.$response->body());
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('SendPlaylistVideoToTelegram: all retries exhausted.', [
            'video_id' => $this->video->id,
            'chat_id' => $this->chatId,
            'error' => $e->getMessage(),
        ]);
    }
}

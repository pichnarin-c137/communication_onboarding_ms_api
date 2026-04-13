<?php

namespace App\Jobs;

use App\Models\Playlist;
use App\Models\PlaylistVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPlaylistToTelegram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly Playlist $playlist,
        public readonly string $chatId,
        public readonly ?string $message = null,
    ) {}

    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token', '');

        if (! $botToken) {
            Log::warning('SendPlaylistToTelegram: bot token not configured.', [
                'playlist_id' => $this->playlist->id,
            ]);
            return;
        }

        $header = "<b>{$this->playlist->title}</b>";
        if ($this->message) {
            $header .= "\n\n{$this->message}";
        }

        $this->post($botToken, $header);

        $videos = PlaylistVideo::where('playlist_id', $this->playlist->id)
            ->orderBy('position')
            ->get();

        foreach ($videos as $video) {
            $this->post($botToken, "<b>{$video->title}</b>\n{$video->youtube_url}");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendPlaylistToTelegram: all retries exhausted.', [
            'playlist_id' => $this->playlist->id,
            'chat_id'     => $this->chatId,
            'error'       => $e->getMessage(),
        ]);
    }

    private function post(string $botToken, string $text): void
    {
        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id'    => $this->chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Telegram API error: " . $response->body());
        }
    }
}

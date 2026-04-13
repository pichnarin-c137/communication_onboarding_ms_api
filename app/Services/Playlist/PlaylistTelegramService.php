<?php

namespace App\Services\Playlist;

use App\Exceptions\Business\PlaylistEmptyException;
use App\Exceptions\Business\PlaylistVideoNotFoundException;
use App\Jobs\SendPlaylistToTelegram;
use App\Jobs\SendPlaylistVideoToTelegram;
use App\Models\Playlist;
use App\Models\PlaylistVideo;

class PlaylistTelegramService
{
    public function sendVideo(Playlist $playlist, PlaylistVideo $video, string $chatId, ?string $message = null): void
    {
        if ($video->playlist_id !== $playlist->id) {
            throw new PlaylistVideoNotFoundException(
                "Video '{$video->id}' does not belong to playlist '{$playlist->id}'.",
                context: ['playlist_id' => $playlist->id, 'video_id' => $video->id]
            );
        }

        SendPlaylistVideoToTelegram::dispatch($video, $chatId, $message)
            ->onQueue(config('coms.telegram_send_queue', 'high'));
    }

    public function sendPlaylist(Playlist $playlist, string $chatId, ?string $message = null): void
    {
        if ($playlist->videos()->count() === 0) {
            throw new PlaylistEmptyException(context: ['playlist_id' => $playlist->id]);
        }

        SendPlaylistToTelegram::dispatch($playlist, $chatId, $message)
            ->onQueue(config('coms.telegram_send_queue', 'high'));
    }
}

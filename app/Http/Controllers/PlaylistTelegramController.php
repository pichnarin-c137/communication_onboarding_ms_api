<?php

namespace App\Http\Controllers;

use App\Exceptions\Business\PlaylistEmptyException;
use App\Exceptions\Business\PlaylistNotFoundException;
use App\Exceptions\Business\PlaylistVideoNotFoundException;
use App\Http\Requests\Playlist\SendToTelegramRequest;
use App\Models\Playlist;
use App\Models\PlaylistVideo;
use App\Services\Playlist\PlaylistTelegramService;
use Illuminate\Http\JsonResponse;

class PlaylistTelegramController extends Controller
{
    public function __construct(
        private readonly PlaylistTelegramService $telegramService
    ) {}

    /**
     * Send a single video to Telegram.
     *
     * POST /playlists/{id}/videos/{vid}/send
     *
     * @throws PlaylistNotFoundException
     * @throws PlaylistVideoNotFoundException
     */
    public function sendVideo(SendToTelegramRequest $request, string $playlistId, string $videoId): JsonResponse
    {
        $playlist = $this->resolvePlaylist($playlistId);
        $video = $this->resolveVideo($playlist, $videoId);

        $this->telegramService->sendVideo(
            $playlist,
            $video,
            $request->input('chat_id'),
            $request->input('message')
        );

        return response()->json([
            'success' => true,
            'message' => 'Video queued for Telegram delivery.',
            'data' => null,
        ], 202);
    }

    /**
     * Send an entire playlist to Telegram.
     *
     * POST /playlists/{id}/send
     * @throws PlaylistNotFoundException|PlaylistEmptyException
     */
    public function sendPlaylist(SendToTelegramRequest $request, string $playlistId): JsonResponse
    {
        $playlist = $this->resolvePlaylist($playlistId);

        $this->telegramService->sendPlaylist(
            $playlist,
            $request->input('chat_id'),
            $request->input('message')
        );

        return response()->json([
            'success' => true,
            'message' => 'Playlist queued for Telegram delivery.',
            'data' => null,
        ], 202);
    }

    /**
     * @throws PlaylistNotFoundException
     */
    private function resolvePlaylist(string $id): Playlist
    {
        $playlist = Playlist::find($id);

        if (! $playlist) {
            throw new PlaylistNotFoundException("Playlist with ID '$id' not found.", context: ['playlist_id' => $id]);
        }

        return $playlist;
    }

    /**
     * @throws PlaylistVideoNotFoundException
     */
    private function resolveVideo(Playlist $playlist, string $videoId): PlaylistVideo
    {
        $video = PlaylistVideo::where('playlist_id', $playlist->id)->find($videoId);

        if (! $video) {
            throw new PlaylistVideoNotFoundException(
                "Video with ID '$videoId' not found in playlist '$playlist->id'.",
                context: ['playlist_id' => $playlist->id, 'video_id' => $videoId]
            );
        }

        return $video;
    }
}

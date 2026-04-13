<?php

namespace App\Http\Controllers;

use App\Exceptions\Business\PlaylistNotFoundException;
use App\Exceptions\Business\PlaylistVideoNotFoundException;
use App\Http\Requests\Playlist\AddPlaylistVideoRequest;
use App\Http\Requests\Playlist\ReorderPlaylistVideosRequest;
use App\Http\Requests\Playlist\UpdatePlaylistVideoRequest;
use App\Models\Playlist;
use App\Models\PlaylistVideo;
use App\Services\Playlist\PlaylistVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaylistVideoController extends Controller
{
    public function __construct(
        private PlaylistVideoService $videoService
    ) {}

    public function index(string $playlistId): JsonResponse
    {
        $playlist = $this->resolvePlaylist($playlistId);
        $videos   = $this->videoService->listForPlaylist($playlist);

        return response()->json([
            'success' => true,
            'message' => 'Videos retrieved successfully.',
            'data'    => $videos,
        ]);
    }

    public function store(AddPlaylistVideoRequest $request, string $playlistId): JsonResponse
    {
        $userId   = $request->get('auth_user_id');
        $playlist = $this->resolvePlaylist($playlistId);
        $video    = $this->videoService->add($playlist, $request->validated(), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Video added to playlist successfully.',
            'data'    => $video,
        ], 201);
    }

    public function show(string $playlistId, string $videoId): JsonResponse
    {
        $playlist = $this->resolvePlaylist($playlistId);
        $video    = $this->videoService->get($playlist, $videoId);

        return response()->json([
            'success' => true,
            'message' => 'Video retrieved successfully.',
            'data'    => $video,
        ]);
    }

    public function update(UpdatePlaylistVideoRequest $request, string $playlistId, string $videoId): JsonResponse
    {
        $userId   = $request->get('auth_user_id');
        $playlist = $this->resolvePlaylist($playlistId);
        $video    = $this->resolveVideo($playlist, $videoId);
        $video    = $this->videoService->update($playlist, $video, $request->validated(), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Video updated successfully.',
            'data'    => $video,
        ]);
    }

    public function destroy(Request $request, string $playlistId, string $videoId): JsonResponse
    {
        $userId   = $request->get('auth_user_id');
        $playlist = $this->resolvePlaylist($playlistId);
        $video    = $this->resolveVideo($playlist, $videoId);

        $this->videoService->delete($playlist, $video, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Video removed from playlist successfully.',
            'data'    => null,
        ]);
    }

    public function reorder(ReorderPlaylistVideosRequest $request, string $playlistId): JsonResponse
    {
        $userId   = $request->get('auth_user_id');
        $playlist = $this->resolvePlaylist($playlistId);

        $this->videoService->reorder($playlist, $request->validated()['videos'], $userId);

        $videos = $this->videoService->listForPlaylist($playlist);

        return response()->json([
            'success' => true,
            'message' => 'Videos reordered successfully.',
            'data'    => $videos,
        ]);
    }

    private function resolvePlaylist(string $id): Playlist
    {
        $playlist = Playlist::find($id);

        if (! $playlist) {
            throw new PlaylistNotFoundException("Playlist with ID '{$id}' not found.", context: ['playlist_id' => $id]);
        }

        return $playlist;
    }

    private function resolveVideo(Playlist $playlist, string $videoId): PlaylistVideo
    {
        $video = PlaylistVideo::where('playlist_id', $playlist->id)->find($videoId);

        if (! $video) {
            throw new PlaylistVideoNotFoundException(
                "Video with ID '{$videoId}' not found in playlist '{$playlist->id}'.",
                context: ['playlist_id' => $playlist->id, 'video_id' => $videoId]
            );
        }

        return $video;
    }
}

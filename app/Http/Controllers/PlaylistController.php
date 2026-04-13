<?php

namespace App\Http\Controllers;

use App\Exceptions\Business\PlaylistNotFoundException;
use App\Http\Requests\Playlist\CreatePlaylistRequest;
use App\Http\Requests\Playlist\UpdatePlaylistRequest;
use App\Models\Playlist;
use App\Services\Playlist\PlaylistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    public function __construct(
        private PlaylistService $playlistService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId  = $request->get('auth_user_id');
        $filters = $request->only(['is_public']);
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $page    = max(1, (int) $request->input('page', 1));

        $result = $this->playlistService->list($userId, $filters, $perPage, $page);

        return response()->json([
            'success' => true,
            'message' => 'Playlists retrieved successfully.',
            'data'    => $result['data'],
            'meta'    => $result['meta'],
        ]);
    }

    public function store(CreatePlaylistRequest $request): JsonResponse
    {
        $userId   = $request->get('auth_user_id');
        $playlist = $this->playlistService->create($request->validated(), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Playlist created successfully.',
            'data'    => $playlist,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $playlist = $this->playlistService->get($id);

        return response()->json([
            'success' => true,
            'message' => 'Playlist retrieved successfully.',
            'data'    => $playlist,
        ]);
    }

    public function update(UpdatePlaylistRequest $request, string $id): JsonResponse
    {
        $userId   = $request->get('auth_user_id');
        $playlist = $this->resolvePlaylist($id);
        $playlist = $this->playlistService->update($playlist, $request->validated(), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Playlist updated successfully.',
            'data'    => $playlist,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId   = $request->get('auth_user_id');
        $playlist = $this->resolvePlaylist($id);

        $this->playlistService->delete($playlist, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Playlist deleted successfully.',
            'data'    => null,
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
}

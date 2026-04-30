<?php

namespace App\Services\Playlist;

use App\Exceptions\Business\PlaylistNotFoundException;
use App\Models\Playlist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlaylistService
{
    //
    // Read operations (cached)
    //

    public function list(string $userId, array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $cacheKey = $this->listCacheKey($userId);
        $ttl = config('coms.playlist_list_ttl', 300);

        $all = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($userId) {
            return Playlist::with(['creator'])
                ->withCount(['videos as video_count'])
                ->where('created_by', $userId)
                ->orderBy('created_at')
                ->get();
        });

        $filtered = $all
            ->when(isset($filters['is_public']), fn ($c) => $c->where('is_public', (bool) $filters['is_public']))
            ->values();

        $total = $filtered->count();
        $items = $filtered->forPage($page, $perPage)->values();

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'from' => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
        ];
    }

    public function get(string $id): Playlist
    {
        $playlist = Playlist::with(['creator', 'videos'])
            ->withCount(['videos as video_count'])
            ->find($id);

        if (! $playlist) {
            throw new PlaylistNotFoundException("Playlist with ID '{$id}' not found.", context: ['playlist_id' => $id]);
        }

        return $playlist;
    }

    //
    // Write operations
    //

    public function create(array $data, string $userId): Playlist
    {
        $playlist = Playlist::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_public' => $data['is_public'] ?? false,
            'created_by' => $userId,
        ]);

        $this->invalidateListCache($userId);

        return $playlist->load(['creator'])->loadCount(['videos as video_count']);
    }

    public function update(Playlist $playlist, array $data, string $userId): Playlist
    {
        $updateData = array_filter([
            'title' => $data['title'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
            'is_public' => $data['is_public'] ?? null,
            'updated_by' => $userId,
        ], fn ($v) => ! is_null($v));

        // description can be explicitly set to null — handle that case separately
        if (array_key_exists('description', $data) && $data['description'] === null) {
            $updateData['description'] = null;
        }

        $playlist->update($updateData);
        $this->invalidate($playlist->id, $playlist->created_by);

        $playlist = $playlist->fresh(['creator', 'videos']);

        return $playlist->loadCount(['videos as video_count']);
    }

    public function delete(Playlist $playlist, string $userId): void
    {
        DB::transaction(function () use ($playlist, $userId) {
            // Soft-delete all child videos in the same transaction
            $playlist->videos()->each(function ($video) use ($userId) {
                $video->update(['deleted_by' => $userId]);
                $video->delete();
            });

            $playlist->update(['deleted_by' => $userId]);
            $playlist->delete();
        });

        $this->invalidate($playlist->id, $playlist->created_by);
    }

    //
    // Cache helpers
    //

    public function invalidate(string $playlistId, string $userId): void
    {
        $this->invalidateListCache($userId);
        Cache::store('redis')->forget("playlist:{$playlistId}:videos");
    }

    private function invalidateListCache(string $userId): void
    {
        Cache::store('redis')->forget($this->listCacheKey($userId));
    }

    private function listCacheKey(string $userId): string
    {
        return "playlist:list:{$userId}";
    }
}

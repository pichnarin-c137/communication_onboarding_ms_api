<?php

namespace App\Services\Playlist;

use App\Exceptions\Business\DuplicatePlaylistVideoException;
use App\Exceptions\Business\InvalidYouTubeLinkException;
use App\Exceptions\Business\PlaylistVideoNotFoundException;
use App\Models\Playlist;
use App\Models\PlaylistVideo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlaylistVideoService
{
    //
    // Read operations (cached)
    //

    public function listForPlaylist(Playlist $playlist): Collection
    {
        $cacheKey = "playlist:$playlist->id:videos";
        $ttl = config('coms.playlist_list_ttl', 300);

        return Cache::store('redis')->remember($cacheKey, $ttl, function () use ($playlist) {
            return $playlist->videos()->with(['creator'])->orderBy('position')->get();
        });
    }

    /**
     * @throws PlaylistVideoNotFoundException
     */
    public function get(Playlist $playlist, string $videoId): PlaylistVideo
    {
        $video = PlaylistVideo::where('playlist_id', $playlist->id)->find($videoId);

        if (! $video) {
            throw new PlaylistVideoNotFoundException(
                "Video with ID '$videoId' not found in playlist '$playlist->id'.",
                context: ['playlist_id' => $playlist->id, 'video_id' => $videoId]
            );
        }

        return $video->load(['creator']);
    }

    //
    // Write operations
    //

    /**
     * @throws DuplicatePlaylistVideoException
     * @throws InvalidYouTubeLinkException
     */
    public function add(Playlist $playlist, array $data, string $userId): PlaylistVideo
    {
        $this->assertValidYouTubeUrl($data['youtube_url']);
        $this->assertYouTubeVideoIsUniqueGlobally($data['youtube_url']);

        $position = $data['position'] ?? $this->nextPosition($playlist->id);

        $video = PlaylistVideo::create([
            'playlist_id' => $playlist->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'youtube_url' => $data['youtube_url'],
            'position' => $position,
            'created_by' => $userId,
        ]);

        $this->invalidateCaches($playlist);

        return $video->load(['creator']);
    }

    /**
     * @throws DuplicatePlaylistVideoException
     * @throws InvalidYouTubeLinkException
     */
    public function update(Playlist $playlist, PlaylistVideo $video, array $data, string $userId): PlaylistVideo
    {
        if (isset($data['youtube_url'])) {
            $this->assertValidYouTubeUrl($data['youtube_url']);
            $this->assertYouTubeVideoIsUniqueGlobally($data['youtube_url'], $video->id);
        }

        $updateData = ['updated_by' => $userId];

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['youtube_url'])) {
            $updateData['youtube_url'] = $data['youtube_url'];
        }
        if (isset($data['position'])) {
            $updateData['position'] = $data['position'];
        }

        $video->update($updateData);
        $this->invalidateCaches($playlist);

        return $video->fresh(['creator']);
    }

    public function delete(Playlist $playlist, PlaylistVideo $video, string $userId): void
    {
        $video->update(['deleted_by' => $userId]);
        $video->delete();

        $this->invalidateCaches($playlist);
    }

    /**
     * @throws Throwable
     */
    public function reorder(Playlist $playlist, array $videoPositions, string $userId): void
    {
        DB::transaction(function () use ($playlist, $videoPositions, $userId) {
            foreach ($videoPositions as $item) {
                PlaylistVideo::where('id', $item['id'])
                    ->where('playlist_id', $playlist->id)
                    ->update([
                        'position' => $item['position'],
                        'updated_by' => $userId,
                    ]);
            }
        });

        $this->invalidateCaches($playlist);
    }

    //
    // Helpers
    //

    /**
     * @throws InvalidYouTubeLinkException
     */
    private function assertValidYouTubeUrl(string $url): void
    {
        if (! $this->extractYouTubeVideoId($url)) {
            throw new InvalidYouTubeLinkException(
                'The YouTube URL must be a valid https://www.youtube.com/watch?... or https://youtu.be/... link.',
                context: ['youtube_url' => $url]
            );
        }
    }

    /**
     * @throws DuplicatePlaylistVideoException
     * @throws InvalidYouTubeLinkException
     */
    private function assertYouTubeVideoIsUniqueGlobally(string $url, ?string $ignoreVideoId = null): void
    {
        $incomingVideoId = $this->extractYouTubeVideoId($url);

        if (! $incomingVideoId) {
            throw new InvalidYouTubeLinkException(
                'The provided URL is not a valid YouTube link.',
                context: ['youtube_url' => $url]
            );
        }

        $existingVideos = PlaylistVideo::query()
            ->when($ignoreVideoId, fn ($query) => $query->where('id', '!=', $ignoreVideoId))
            ->get(['id', 'playlist_id', 'youtube_url']);

        foreach ($existingVideos as $existingVideo) {
            if ($this->extractYouTubeVideoId($existingVideo->youtube_url) === $incomingVideoId) {
                throw new DuplicatePlaylistVideoException(
                    'This YouTube video already exists in another playlist.',
                    context: [
                        'video_id' => $incomingVideoId,
                        'youtube_url' => $url,
                        'duplicate_video_row_id' => $existingVideo->id,
                        'duplicate_playlist_id' => $existingVideo->playlist_id,
                    ]
                );
            }
        }
    }

    private function extractYouTubeVideoId(string $url): ?string
    {
        $parsed = parse_url(trim($url));

        if (! is_array($parsed) || empty($parsed['host'])) {
            return null;
        }

        $host = strtolower($parsed['host']);
        $path = $parsed['path'] ?? '';

        if (in_array($host, ['www.youtube.com', 'youtube.com', 'm.youtube.com'], true)) {
            parse_str($parsed['query'] ?? '', $query);
            $videoId = $query['v'] ?? null;

            return $this->normalizeYouTubeVideoId($videoId);
        }

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $segments = explode('/', trim($path, '/'));
            $videoId = $segments[0] ?? null;

            return $this->normalizeYouTubeVideoId($videoId);
        }

        return null;
    }

    private function normalizeYouTubeVideoId(mixed $videoId): ?string
    {
        if (! is_string($videoId)) {
            return null;
        }

        $videoId = trim($videoId);

        if (! preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
            return null;
        }

        return $videoId;
    }

    private function nextPosition(string $playlistId): int
    {
        $max = PlaylistVideo::where('playlist_id', $playlistId)->max('position');

        return ($max ?? 0) + 1;
    }

    private function invalidateCaches(Playlist $playlist): void
    {
        Cache::store('redis')->forget("playlist:$playlist->id:videos");
        Cache::store('redis')->forget("playlist:list:$playlist->created_by");
    }
}

<?php

namespace App\Services\Analytics\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Caching wrapper for analytics responses.
 *
 * Strategy:
 *   - Use the cache store named in config('coms.analytics.cache_store') (default: redis).
 *   - Tag every entry with ['analytics', "analytics:user:{userId}", "analytics:user:admin"]
 *     so AnalyticsCacheObserver can flush by tag when underlying entities change.
 *   - If the store doesn't support tags (e.g. database/file), fall back to direct
 *     remember() with no tag invalidation — TTL-only. Logged once per process.
 */
final class AnalyticsCache
{
    private static bool $tagSupportWarned = false;

    public function remember(string $key, AnalyticsScope $scope, int $ttl, Closure $fn): mixed
    {
        try {
            $store = $this->store();

            if ($store->getStore() instanceof TaggableStore) {
                return $store->tags($this->tagsFor($scope))->remember($key, $ttl, $fn);
            }
        } catch (Throwable $e) {
            Log::warning('analytics_cache.tag_unavailable', [
                'error' => $e->getMessage(),
            ]);
        }

        if (! self::$tagSupportWarned) {
            self::$tagSupportWarned = true;
            Log::info('analytics_cache.untagged_fallback', [
                'store' => config('coms.analytics.cache_store', 'redis'),
            ]);
        }

        try {
            return $this->store()->remember($key, $ttl, $fn);
        } catch (Throwable $e) {
            Log::warning('analytics_cache.read_failed', ['key' => $key, 'error' => $e->getMessage()]);
            return $fn();
        }
    }

    /**
     * Flush every analytics cache entry tagged for the given user.
     * Safe to call on any cache store — silently no-ops if tags aren't supported.
     */
    public function flushForUser(string $userId): void
    {
        try {
            $store = $this->store();
            if ($store->getStore() instanceof TaggableStore) {
                $store->tags("analytics:user:{$userId}")->flush();
            }
        } catch (Throwable $e) {
            Log::warning('analytics_cache.flush_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function flushAdmin(): void
    {
        $this->flushForUser('admin');
    }

    private function store()
    {
        $name = config('coms.analytics.cache_store');

        return $name ? Cache::store($name) : Cache::store();
    }

    private function tagsFor(AnalyticsScope $scope): array
    {
        return [
            'analytics',
            "analytics:user:{$scope->userId}",
        ];
    }
}

<?php

namespace App\Services;

use App\Models\UserSetting;
use Illuminate\Support\Facades\Cache;

class UserSettingsService
{
    public function getSettings(string $userId): UserSetting
    {
        return Cache::remember(
            $this->cacheKey($userId),
            config('coms.user_settings.cache_ttl', 600),
            fn () => $this->findOrCreateDefaults($userId)
        );
    }

    public function updateSettings(string $userId, array $data): UserSetting
    {
        $settings = $this->findOrCreateDefaults($userId);
        $settings->update($data);

        $this->invalidateCache($userId);

        return $settings->fresh();
    }

    public function shouldDeliver(string $userId, string $channel): bool
    {
        $settings = $this->getSettings($userId);

        $channelEnabled = match ($channel) {
            'in_app' => $settings->in_app_notifications,
            'telegram' => $settings->telegram_notifications,
            default => true,
        };

        if (! $channelEnabled) {
            return false;
        }

        return ! $settings->isInQuietHours();
    }

    public function isInQuietHours(string $userId): bool
    {
        return $this->getSettings($userId)->isInQuietHours();
    }

    private function findOrCreateDefaults(string $userId): UserSetting
    {
        return UserSetting::firstOrCreate(
            ['user_id' => $userId],
            config('coms.user_settings.defaults', [])
        );
    }

    private function invalidateCache(string $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    private function cacheKey(string $userId): string
    {
        return "user_settings:{$userId}";
    }
}

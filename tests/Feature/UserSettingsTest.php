<?php

namespace Tests\Feature;

use App\Models\UserSetting;
use App\Services\UserSettingsService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    /** @test */
    public function it_returns_default_settings_on_first_access(): void
    {
        $user = $this->createUser(['role' => 'sale']);

        $response = $this->getJson('/api/v1/settings', $this->authHeadersFor($user));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id'                => $user->id,
                    'in_app_notifications'   => true,
                    'telegram_notifications' => true,
                    'language'               => 'en',
                    'timezone'               => 'Asia/Phnom_Penh',
                    'items_per_page'         => 15,
                    'theme'                  => 'light',
                    'quiet_hours_enabled'    => false,
                    'quiet_hours_start'      => '22:00',
                    'quiet_hours_end'        => '07:00',
                ],
            ]);

        $this->assertDatabaseHas('user_settings', ['user_id' => $user->id]);
    }

    /** @test */
    public function it_updates_notification_toggles(): void
    {
        $user = $this->createUser(['role' => 'trainer']);

        $response = $this->patchJson('/api/v1/settings', [
            'in_app_notifications'   => false,
            'telegram_notifications' => false,
        ], $this->authHeadersFor($user));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'in_app_notifications'   => false,
                    'telegram_notifications' => false,
                ],
            ]);

        $this->assertDatabaseHas('user_settings', [
            'user_id'                => $user->id,
            'in_app_notifications'   => false,
            'telegram_notifications' => false,
        ]);
    }

    /** @test */
    public function it_updates_language_and_timezone(): void
    {
        $user = $this->createUser(['role' => 'sale']);

        $response = $this->patchJson('/api/v1/settings', [
            'language' => 'km',
            'timezone' => 'Asia/Bangkok',
        ], $this->authHeadersFor($user));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'language' => 'km',
                    'timezone' => 'Asia/Bangkok',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_invalid_language(): void
    {
        $user = $this->createUser(['role' => 'sale']);

        $response = $this->patchJson('/api/v1/settings', [
            'language' => 'fr',
        ], $this->authHeadersFor($user));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    }

    /** @test */
    public function it_rejects_invalid_timezone(): void
    {
        $user = $this->createUser(['role' => 'sale']);

        $response = $this->patchJson('/api/v1/settings', [
            'timezone' => 'Invalid/Timezone',
        ], $this->authHeadersFor($user));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timezone']);
    }

    /** @test */
    public function it_rejects_items_per_page_out_of_range(): void
    {
        $user = $this->createUser(['role' => 'sale']);

        $this->patchJson('/api/v1/settings', [
            'items_per_page' => 2,
        ], $this->authHeadersFor($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items_per_page']);

        $this->patchJson('/api/v1/settings', [
            'items_per_page' => 200,
        ], $this->authHeadersFor($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items_per_page']);
    }

    /** @test */
    public function it_updates_quiet_hours(): void
    {
        $user = $this->createUser(['role' => 'trainer']);

        $response = $this->patchJson('/api/v1/settings', [
            'quiet_hours_enabled' => true,
            'quiet_hours_start'   => '23:00',
            'quiet_hours_end'     => '06:00',
        ], $this->authHeadersFor($user));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'quiet_hours_enabled' => true,
                    'quiet_hours_start'   => '23:00',
                    'quiet_hours_end'     => '06:00',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_invalid_time_format(): void
    {
        $user = $this->createUser(['role' => 'sale']);

        $this->patchJson('/api/v1/settings', [
            'quiet_hours_start' => '25:00',
        ], $this->authHeadersFor($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quiet_hours_start']);

        $this->patchJson('/api/v1/settings', [
            'quiet_hours_end' => 'invalid',
        ], $this->authHeadersFor($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quiet_hours_end']);
    }

    /** @test */
    public function it_updates_display_preferences(): void
    {
        $user = $this->createUser(['role' => 'sale']);

        $response = $this->patchJson('/api/v1/settings', [
            'theme'          => 'dark',
            'items_per_page' => 50,
        ], $this->authHeadersFor($user));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'theme'          => 'dark',
                    'items_per_page' => 50,
                ],
            ]);
    }

    /** @test */
    public function it_rejects_invalid_theme(): void
    {
        $user = $this->createUser(['role' => 'sale']);

        $this->patchJson('/api/v1/settings', [
            'theme' => 'blue',
        ], $this->authHeadersFor($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['theme']);
    }

    /** @test */
    public function quiet_hours_overnight_range_works_correctly(): void
    {
        $user = $this->createUser(['role' => 'trainer']);
        $settings = UserSetting::create([
            'user_id'             => $user->id,
            'quiet_hours_enabled' => true,
            'quiet_hours_start'   => '22:00',
            'quiet_hours_end'     => '07:00',
            'timezone'            => 'UTC',
        ]);

        // Mock time to 23:00 UTC — should be in quiet hours
        $this->travelTo(now()->setTimezone('UTC')->setTime(23, 0));
        $this->assertTrue($settings->isInQuietHours());

        // Mock time to 02:00 UTC — should be in quiet hours
        $this->travelTo(now()->setTimezone('UTC')->setTime(2, 0));
        $this->assertTrue($settings->isInQuietHours());

        // Mock time to 12:00 UTC — should NOT be in quiet hours
        $this->travelTo(now()->setTimezone('UTC')->setTime(12, 0));
        $this->assertFalse($settings->isInQuietHours());
    }

    /** @test */
    public function should_deliver_respects_channel_toggle(): void
    {
        $user = $this->createUser(['role' => 'sale']);
        UserSetting::create([
            'user_id'              => $user->id,
            'in_app_notifications' => false,
            'telegram_notifications' => true,
        ]);

        $service = app(UserSettingsService::class);

        $this->assertFalse($service->shouldDeliver($user->id, 'in_app'));
        $this->assertTrue($service->shouldDeliver($user->id, 'telegram'));
    }

    /** @test */
    public function should_deliver_respects_quiet_hours(): void
    {
        $user = $this->createUser(['role' => 'trainer']);
        UserSetting::create([
            'user_id'              => $user->id,
            'in_app_notifications' => true,
            'quiet_hours_enabled'  => true,
            'quiet_hours_start'    => '22:00',
            'quiet_hours_end'      => '07:00',
            'timezone'             => 'UTC',
        ]);

        $service = app(UserSettingsService::class);

        // During quiet hours
        $this->travelTo(now()->setTimezone('UTC')->setTime(23, 0));
        $this->assertFalse($service->shouldDeliver($user->id, 'in_app'));

        // Outside quiet hours
        $this->travelTo(now()->setTimezone('UTC')->setTime(12, 0));
        $this->assertTrue($service->shouldDeliver($user->id, 'in_app'));
    }

    /** @test */
    public function settings_are_cached_and_invalidated_on_update(): void
    {
        $user = $this->createUser(['role' => 'sale']);
        $service = app(UserSettingsService::class);

        // First access creates and caches
        $settings = $service->getSettings($user->id);
        $this->assertTrue(Cache::has("user_settings:{$user->id}"));
        $this->assertEquals('en', $settings->language);

        // Update should invalidate cache
        $updated = $service->updateSettings($user->id, ['language' => 'km']);
        $this->assertFalse(Cache::has("user_settings:{$user->id}"));
        $this->assertEquals('km', $updated->language);

        // Re-fetch should re-cache
        $service->getSettings($user->id);
        $this->assertTrue(Cache::has("user_settings:{$user->id}"));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_settings(): void
    {
        $this->getJson('/api/v1/settings')->assertStatus(401);
        $this->patchJson('/api/v1/settings', ['theme' => 'dark'])->assertStatus(401);
    }
}

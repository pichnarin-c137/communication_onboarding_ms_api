<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Str;

class BroadcastAuthTest extends TestCase
{
    /** @test */
    public function it_returns_401_without_a_jwt_token(): void
    {
        $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id'    => '1234.5678',
            'channel_name' => 'private-notifications.some-id',
        ])->assertStatus(401);
    }

    /** @test */
    public function it_returns_422_when_socket_id_is_missing(): void
    {
        $user = $this->createUser(['role' => 'sale']);
        $this->postJson(
            '/api/v1/broadcasting/auth',
            ['channel_name' => "private-notifications.{$user->id}"],
            $this->authHeadersFor($user)
        )->assertStatus(422);
    }

    /** @test */
    public function it_returns_422_when_channel_name_is_missing(): void
    {
        $user = $this->createUser(['role' => 'sale']);
        $this->postJson(
            '/api/v1/broadcasting/auth',
            ['socket_id' => '1234.5678'],
            $this->authHeadersFor($user)
        )->assertStatus(422);
    }

    /** @test */
    public function it_returns_403_when_requesting_another_users_channel(): void
    {
        $user    = $this->createUser(['role' => 'sale']);
        $otherId = (string) Str::uuid();

        $this->postJson(
            '/api/v1/broadcasting/auth',
            ['socket_id' => '1234.5678', 'channel_name' => "private-notifications.{$otherId}"],
            $this->authHeadersFor($user)
        )->assertStatus(403)
         ->assertJson(['success' => false, 'error_code' => 'BROADCAST_AUTH_FORBIDDEN']);
    }

    /** @test */
    public function it_returns_403_for_non_notification_channels(): void
    {
        $user = $this->createUser(['role' => 'sale']);
        $this->postJson(
            '/api/v1/broadcasting/auth',
            ['socket_id' => '1234.5678', 'channel_name' => "private-other.{$user->id}"],
            $this->authHeadersFor($user)
        )->assertStatus(403);
    }

    /** @test */
    public function it_returns_signed_pusher_auth_for_own_channel(): void
    {
        $user = $this->createUser(['role' => 'sale']);
        $response = $this->postJson(
            '/api/v1/broadcasting/auth',
            ['socket_id' => '1234.5678', 'channel_name' => "private-notifications.{$user->id}"],
            $this->authHeadersFor($user)
        );

        $response->assertStatus(200)
                 ->assertJsonStructure(['auth']);
        // auth value is "APP_KEY:HMAC_SIGNATURE"
        $this->assertStringContainsString(':', $response->json('auth'));
    }
}

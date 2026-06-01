<?php

namespace Tests\Feature;

use App\Services\Logging\ActivityLogger;
use Tests\TestCase;

class ForceLoginTest extends TestCase
{
    private function url(string $userId): string
    {
        return "/api/v1/users/{$userId}/force-login";
    }

    /** @test */
    public function admin_can_force_login_as_a_trainer(): void
    {
        $admin = $this->createAdmin();
        $trainer = $this->createUser(['role' => 'trainer']);

        $response = $this->postJson($this->url($trainer->id), [], $this->authHeadersFor($admin));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Force login successful',
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'id'   => $trainer->id,
                        'role' => 'trainer',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'expires_in', 'refresh_expires_in', 'user'],
            ])
            // Body-only delivery — the admin's own session cookie must not be replaced.
            ->assertCookieMissing('refresh_token');
    }

    /** @test */
    public function admin_can_force_login_as_a_sale(): void
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);

        $this->postJson($this->url($sale->id), [], $this->authHeadersFor($admin))
            ->assertStatus(200)
            ->assertJsonPath('data.user.role', 'sale');
    }

    /** @test */
    public function force_login_writes_an_audit_log_attributed_to_the_admin(): void
    {
        $admin = $this->createAdmin();
        $trainer = $this->createUser(['role' => 'trainer']);

        $this->postJson($this->url($trainer->id), [], $this->authHeadersFor($admin))
            ->assertStatus(200);

        $log = \App\Models\UserActivityLog::where('action', ActivityLogger::ADMIN_FORCE_LOGIN)->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($trainer->id, $log->metadata['target_user_id']);
        $this->assertSame('trainer', $log->metadata['target_role']);
    }

    /** @test */
    public function admin_cannot_force_login_as_another_admin(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin();

        $this->postJson($this->url($otherAdmin->id), [], $this->authHeadersFor($admin))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'FORCE_LOGIN_TARGET_FORBIDDEN');
    }

    /** @test */
    public function admin_cannot_force_login_as_a_plain_user(): void
    {
        $admin = $this->createAdmin();
        $plainUser = $this->createUser(['role' => 'user']);

        $this->postJson($this->url($plainUser->id), [], $this->authHeadersFor($admin))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'FORCE_LOGIN_TARGET_FORBIDDEN');
    }

    /** @test */
    public function admin_cannot_force_login_as_a_suspended_user(): void
    {
        $admin = $this->createAdmin();
        $suspended = $this->createSuspendedUser(['role' => 'trainer']);

        $this->postJson($this->url($suspended->id), [], $this->authHeadersFor($admin))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
    }

    /** @test */
    public function force_login_returns_404_for_unknown_user(): void
    {
        $admin = $this->createAdmin();

        $this->postJson($this->url('00000000-0000-0000-0000-000000000000'), [], $this->authHeadersFor($admin))
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'USER_NOT_FOUND');
    }

    /** @test */
    public function non_admin_cannot_force_login(): void
    {
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);

        $this->postJson($this->url($trainer->id), [], $this->authHeadersFor($sale))
            ->assertStatus(403);
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Helpers\CreatesUsers;
use Tests\Helpers\GeneratesTokens;
use Tests\Helpers\HandlesOtp;

abstract class TestCase extends BaseTestCase
{
    use CreatesUsers;
    use GeneratesTokens;
    use HandlesOtp;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure JWT keys exist for testing
        $this->ensureJwtKeysExist();

        // Seed roles before each test
        $this->seedRoles();
    }

    /**
     * Ensure JWT keys exist for testing
     */
    protected function ensureJwtKeysExist(): void
    {
        $privateKeyPath = storage_path('keys/jwt_private.pem');
        $publicKeyPath = storage_path('keys/jwt_public.pem');

        if (! file_exists($privateKeyPath) || ! file_exists($publicKeyPath)) {
            $this->artisan('jwt:generate-keys');
        }
    }

    /**
     * Seed admin and user roles
     */
    protected function seedRoles(): void
    {
        \App\Models\Role::firstOrCreate(['role' => 'admin']);
        \App\Models\Role::firstOrCreate(['role' => 'user']);
    }
}

<?php

namespace Tests\Helpers;

use App\Models\Credential;
use App\Models\Role;
use App\Models\User;

trait CreatesUsers
{
    /**
     * Create a user with credentials
     */
    protected function createUser(array $userData = [], array $credentialData = []): User
    {
        $roleName = $userData['role'] ?? 'user';
        $role = Role::where('role', $roleName)->first();

        if (! $role) {
            $role = Role::factory()->create(['role' => $roleName]);
        }

        $user = User::factory()->create(array_merge([
            'role_id' => $role->id,
        ], $userData));

        Credential::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $credentialData));

        return $user->load(['role', 'credential']);
    }

    /**
     * Create an admin user
     */
    protected function createAdmin(array $userData = [], array $credentialData = []): User
    {
        return $this->createUser(
            array_merge(['role' => 'admin'], $userData),
            $credentialData
        );
    }

    /**
     * Create a suspended user
     */
    protected function createSuspendedUser(array $userData = [], array $credentialData = []): User
    {
        return $this->createUser(
            array_merge(['is_suspended' => true], $userData),
            $credentialData
        );
    }
}

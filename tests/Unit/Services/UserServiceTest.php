<?php

namespace Tests\Unit\Services;

use App\Exceptions\RoleNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = app(UserService::class);
    }

    /** @test */
    public function it_creates_user_with_correct_role()
    {
        Mail::fake();

        $userData = [
            'role' => 'admin',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => '1990-01-01',
            'address' => '123 Main St',
            'gender' => 'male',
            'nationality' => 'USA',
        ];

        $credentialData = [
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'phone_number' => '+11234567890',
            'password' => 'Test@12345',
        ];

        $user = $this->userService->createUser($userData, $credentialData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('admin', $user->role->role);
        $this->assertEquals('John', $user->first_name);
        $this->assertEquals('Doe', $user->last_name);
    }

    /** @test */
    public function it_creates_associated_credential()
    {
        Mail::fake();

        $userData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'dob' => '1992-05-15',
            'address' => '456 Oak Ave',
            'gender' => 'female',
            'nationality' => 'Canada',
        ];

        $credentialData = [
            'email' => 'jane@example.com',
            'username' => 'janesmith',
            'phone_number' => '+19876543210',
            'password' => 'Secure@Pass123',
        ];

        $user = $this->userService->createUser($userData, $credentialData);

        $this->assertNotNull($user->credential);
        $this->assertEquals('jane@example.com', $user->credential->email);
        $this->assertEquals('janesmith', $user->credential->username);
        $this->assertEquals('+19876543210', $user->credential->phone_number);
    }

    /** @test */
    public function it_hashes_password_when_creating_user()
    {
        Mail::fake();

        $userData = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'dob' => '1995-03-20',
            'address' => '789 Elm St',
            'gender' => 'other',
            'nationality' => 'UK',
        ];

        $credentialData = [
            'email' => 'test@example.com',
            'username' => 'testuser',
            'phone_number' => '+15551234567',
            'password' => 'PlainPassword@123',
        ];

        $user = $this->userService->createUser($userData, $credentialData);

        $this->assertNotEquals('PlainPassword@123', $user->credential->password);
        $this->assertTrue(Hash::check('PlainPassword@123', $user->credential->password));
    }

    /** @test */
    public function it_sends_otp_to_email_when_creating_user()
    {
        Mail::fake();

        $userData = [
            'first_name' => 'OTP',
            'last_name' => 'Test',
            'dob' => '1988-07-10',
            'address' => '321 Pine St',
            'gender' => 'male',
            'nationality' => 'Australia',
        ];

        $credentialData = [
            'email' => 'otp@example.com',
            'username' => 'otptest',
            'phone_number' => '+14445556666',
            'password' => 'Otp@Password456',
        ];

        $user = $this->userService->createUser($userData, $credentialData);

        Mail::assertSent(\App\Mail\OtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->credential->email);
        });

        $user->credential->refresh();
        $this->assertNotNull($user->credential->otp);
        $this->assertNotNull($user->credential->otp_expiry);
    }

    /** @test */
    public function it_throws_exception_for_invalid_role()
    {
        $this->expectException(RoleNotFoundException::class);
        $this->expectExceptionMessage("Role 'invalid_role' not found");

        Mail::fake();

        $userData = [
            'role' => 'invalid_role',
            'first_name' => 'Invalid',
            'last_name' => 'Role',
            'dob' => '1990-01-01',
            'address' => '123 Main St',
            'gender' => 'male',
            'nationality' => 'USA',
        ];

        $credentialData = [
            'email' => 'invalid@example.com',
            'username' => 'invalid',
            'phone_number' => '+11111111111',
            'password' => 'Test@12345',
        ];

        $this->userService->createUser($userData, $credentialData);
    }

    /** @test */
    public function it_defaults_to_user_role_when_not_specified()
    {
        Mail::fake();

        $userData = [
            // No role specified, should default to 'user'
            'first_name' => 'Default',
            'last_name' => 'User',
            'dob' => '1991-06-15',
            'address' => '999 Default St',
            'gender' => 'female',
            'nationality' => 'Germany',
        ];

        $credentialData = [
            'email' => 'default@example.com',
            'username' => 'defaultuser',
            'phone_number' => '+12223334444',
            'password' => 'Default@Pass789',
        ];

        $user = $this->userService->createUser($userData, $credentialData);

        $this->assertEquals('user', $user->role->role);
    }

    /** @test */
    public function it_stores_user_data_in_database()
    {
        Mail::fake();

        $userData = [
            'first_name' => 'Database',
            'last_name' => 'Test',
            'dob' => '1993-09-25',
            'address' => '777 Database Dr',
            'gender' => 'male',
            'nationality' => 'France',
        ];

        $credentialData = [
            'email' => 'database@example.com',
            'username' => 'dbtest',
            'phone_number' => '+13334445555',
            'password' => 'Database@Pass321',
        ];

        $user = $this->userService->createUser($userData, $credentialData);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Database',
            'last_name' => 'Test',
            'dob' => '1993-09-25',
            'address' => '777 Database Dr',
            'gender' => 'male',
            'nationality' => 'France',
        ]);

        $this->assertDatabaseHas('credentials', [
            'user_id' => $user->id,
            'email' => 'database@example.com',
            'username' => 'dbtest',
            'phone_number' => '+13334445555',
        ]);
    }

    /** @test */
    public function it_returns_complete_user_profile()
    {
        $user = $this->createUser([
            'first_name' => 'Profile',
            'last_name' => 'Test',
            'dob' => '1994-11-30',
            'address' => '555 Profile Ln',
            'gender' => 'female',
            'nationality' => 'Japan',
        ], [
            'email' => 'profile@example.com',
            'username' => 'profiletest',
            'phone_number' => '+16667778888',
        ]);

        $profile = $this->userService->getUserProfile($user->id);

        $this->assertIsArray($profile);
        $this->assertArrayHasKey('id', $profile);
        $this->assertArrayHasKey('first_name', $profile);
        $this->assertArrayHasKey('last_name', $profile);
        $this->assertArrayHasKey('full_name', $profile);
        $this->assertArrayHasKey('dob', $profile);
        $this->assertArrayHasKey('address', $profile);
        $this->assertArrayHasKey('gender', $profile);
        $this->assertArrayHasKey('nationality', $profile);
        $this->assertArrayHasKey('is_suspended', $profile);
        $this->assertArrayHasKey('role', $profile);
        $this->assertArrayHasKey('email', $profile);
        $this->assertArrayHasKey('username', $profile);
        $this->assertArrayHasKey('phone_number', $profile);
        $this->assertArrayHasKey('created_at', $profile);

        $this->assertEquals('Profile', $profile['first_name']);
        $this->assertEquals('Test', $profile['last_name']);
        $this->assertEquals('Profile Test', $profile['full_name']);
        $this->assertEquals('profile@example.com', $profile['email']);
    }

    /** @test */
    public function it_throws_exception_for_non_existent_user_profile()
    {
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found');

        // Use a valid UUID format that doesn't exist
        $this->userService->getUserProfile('00000000-0000-0000-0000-000000000000');
    }

    /** @test */
    public function it_formats_profile_dates_correctly()
    {
        $user = $this->createUser([
            'first_name' => 'Date',
            'last_name' => 'Test',
            'dob' => '1990-12-25',
            'address' => '888 Date St',
            'gender' => 'male',
            'nationality' => 'Spain',
        ], [
            'email' => 'date@example.com',
            'username' => 'datetest',
            'phone_number' => '+17778889999',
        ]);

        $profile = $this->userService->getUserProfile($user->id);

        $this->assertEquals('1990-12-25', $profile['dob']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $profile['created_at']);
    }

    /** @test */
    public function it_suspends_active_user()
    {
        $user = $this->createUser([
            'first_name' => 'Active',
            'last_name' => 'User',
            'is_suspended' => false,
        ]);

        $this->assertFalse($user->is_suspended);

        $updatedUser = $this->userService->toggleSuspension($user->id);

        $this->assertTrue($updatedUser->is_suspended);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_suspended' => true,
        ]);
    }

    /** @test */
    public function it_activates_suspended_user()
    {
        $user = $this->createSuspendedUser();

        $this->assertTrue($user->is_suspended);

        $updatedUser = $this->userService->toggleSuspension($user->id);

        $this->assertFalse($updatedUser->is_suspended);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_suspended' => false,
        ]);
    }

    /** @test */
    public function it_throws_exception_for_non_existent_user_suspension()
    {
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User not found');

        // Use a valid UUID format that doesn't exist
        $this->userService->toggleSuspension('00000000-0000-0000-0000-000000000000');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Credential;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Check if admin already exists
        $existingAdmin = Credential::where('username', 'admin')
            ->orWhere('email', 'pichnarin893@gmail.com')
            ->first();

        if ($existingAdmin) {
            $this->command->info('Admin user already exists: admin');

            return;
        }

        $adminRole = Role::where('role', 'admin')->first();

        $admin = User::create([
            'role_id' => $adminRole->id,
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'dob' => '1990-01-01',
            'address' => '123 Admin Street',
            'gender' => 'other',
            'nationality' => 'Global',
        ]);

        Credential::create([
            'user_id' => $admin->id,
            'email' => 'admin.demo@checkinme.com',
            'username' => 'admin',
            'phone_number' => '+1234567890',
            'password' => Hash::make('Admin@123456'),
        ]);

        $this->command->info('Admin user created: admin / Admin@123456');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Credential;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    // Static UUIDs for demo users
    const SALE_USER_ID = 'bbbbbbbb-0000-0000-0000-000000000001';

    const SALE_USER_IDS = [
        'bbbbbbbb-0000-0000-0000-000000000001',
        'bbbbbbbb-0000-0000-0000-000000000005',
        'bbbbbbbb-0000-0000-0000-000000000006',
        'bbbbbbbb-0000-0000-0000-000000000007',
        'bbbbbbbb-0000-0000-0000-000000000008',
    ];

    const SALE_USERS = [
        [
            'id' => 'bbbbbbbb-0000-0000-0000-000000000001',
            'first_name' => 'Narin',
            'last_name' => 'Pich',
            'dob' => '1992-03-15',
            'address' => '45 Sales Avenue, Phnom Penh',
            'gender' => 'male',
            'nationality' => 'Cambodian',
            'email' => 'pichnarin893@gmail.com',
            'username' => 'narinpich',
            'phone_number' => '+85510000001',
        ],
        [
            'id' => 'bbbbbbbb-0000-0000-0000-000000000005',
            'first_name' => 'Srey',
            'last_name' => 'Meas',
            'dob' => '1993-06-11',
            'address' => '12 Sales Street, Phnom Penh',
            'gender' => 'female',
            'nationality' => 'Cambodian',
            'email' => 'srey.meas@example.com',
            'username' => 'sreymeas',
            'phone_number' => '+85510000003',
        ],
        [
            'id' => 'bbbbbbbb-0000-0000-0000-000000000006',
            'first_name' => 'Vannak',
            'last_name' => 'Sok',
            'dob' => '1991-09-04',
            'address' => '19 Commerce Road, Phnom Penh',
            'gender' => 'male',
            'nationality' => 'Cambodian',
            'email' => 'vannak.sok@example.com',
            'username' => 'vannaksok',
            'phone_number' => '+85510000004',
        ],
        [
            'id' => 'bbbbbbbb-0000-0000-0000-000000000007',
            'first_name' => 'Ratha',
            'last_name' => 'Kim',
            'dob' => '1994-12-22',
            'address' => '77 Business Park, Phnom Penh',
            'gender' => 'female',
            'nationality' => 'Cambodian',
            'email' => 'ratha.kim@example.com',
            'username' => 'rathakim',
            'phone_number' => '+85510000005',
        ],
        [
            'id' => 'bbbbbbbb-0000-0000-0000-000000000008',
            'first_name' => 'Sophea',
            'last_name' => 'Chan',
            'dob' => '1990-01-18',
            'address' => '88 Market Lane, Phnom Penh',
            'gender' => 'female',
            'nationality' => 'Cambodian',
            'email' => 'sophea.chan@example.com',
            'username' => 'sopheachan',
            'phone_number' => '+85510000006',
        ],
    ];

    const TRAINER_USER_ID = 'bbbbbbbb-0000-0000-0000-000000000002'; // Tleang Hour

    const TRAINER_USER_IDS = [
        'bbbbbbbb-0000-0000-0000-000000000003',
        'bbbbbbbb-0000-0000-0000-000000000004',
        'bbbbbbbb-0000-0000-0000-000000012345',
        'bbbbbbbb-1111-2222-3333-444444444446',
        'bbbbbbbb-1111-2222-3333-444444444447',
        'bbbbbbbb-1111-2222-3333-555555555558',
        'bbbbbbbb-0000-0000-0000-000000000011',
        'bbbbbbbb-1111-2222-3333-666666666669',
        'bbbbbbbb-1111-2222-3333-777777777779',
        'bbbbbbbb-1111-2222-3333-888888888889',
    ];

    public function run(): void
    {
        $saleRole = Role::where('role', 'sale')->firstOrFail();
        $trainerRole = Role::where('role', 'trainer')->firstOrFail();

        foreach (self::SALE_USERS as $index => $saleData) {
            $sale = User::updateOrCreate(
                ['id' => $saleData['id']],
                [
                    'role_id' => $saleRole->id,
                    'first_name' => $saleData['first_name'],
                    'last_name' => $saleData['last_name'],
                    'dob' => $saleData['dob'],
                    'address' => $saleData['address'],
                    'gender' => $saleData['gender'],
                    'nationality' => $saleData['nationality'],
                ]
            );

            if (! Credential::where('user_id', $sale->id)->exists()) {
                Credential::create([
                    'user_id' => $sale->id,
                    'email' => $saleData['email'],
                    'username' => $saleData['username'],
                    'phone_number' => $saleData['phone_number'],
                    'password' => Hash::make('1234567890'),
                ]);
            }
        }

        //  Tleang Hour trainer
        $trainer = User::updateOrCreate(
            ['id' => self::TRAINER_USER_ID],
            [
                'role_id' => $trainerRole->id,
                'first_name' => 'Tleang',
                'last_name' => 'Hour',
                'dob' => '1994-07-22',
                'address' => '88 Training Road, Phnom Penh',
                'gender' => 'female',
                'nationality' => 'Cambodian',
            ]
        );

        if (! Credential::where('user_id', $trainer->id)->exists()) {
            Credential::create([
                'user_id' => $trainer->id,
                'email' => 'tleanghour67@gmail.com',
                'username' => 'tleanghour',
                'phone_number' => '+85510000002',
                'password' => Hash::make('1234567890'),
            ]);
        }

        foreach (self::TRAINER_USER_IDS as $index => $trainerId) {
            $firstName = 'Trainer' . ($index + 1);
            $lastName = 'Demo';
            $email = 'trainer' . ($index + 1) . '@example.com';
            $username = 'trainer' . ($index + 1);
            $phone = '+8551000000' . str_pad(($index + 3), 2, '0', STR_PAD_LEFT); // start after +85510000002

            $trainer = User::updateOrCreate(
                ['id' => $trainerId],
                [
                    'role_id' => $trainerRole->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'dob' => '1990-01-' . str_pad(($index + 1), 2, '0', STR_PAD_LEFT),
                    'address' => ($index + 1) . ' Training Road, Phnom Penh',
                    'gender' => $index % 2 === 0 ? 'male' : 'female',
                    'nationality' => 'Cambodian',
                ]
            );

            if (! Credential::where('user_id', $trainer->id)->exists()) {
                Credential::create([
                    'user_id' => $trainer->id,
                    'email' => $email,
                    'username' => $username,
                    'phone_number' => $phone,
                    'password' => Hash::make('1234567890'),
                ]);
            }
        }

        $this->command->info('Demo users seeded: 5 sale users, tleanghour / 1234567890, and 10 additional trainer users.');
    }
}

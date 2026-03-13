<?php

namespace Database\Seeders;

use App\Models\ClientContact;
use Illuminate\Database\Seeder;

class ClientContactSeeder extends Seeder
{
    const ALPHA_CONTACT_1_ID = 'eeeeeeee-0000-0000-0000-000000000001';

    const ALPHA_CONTACT_2_ID = 'eeeeeeee-0000-0000-0000-000000000002';

    const BETA_CONTACT_1_ID = 'eeeeeeee-0000-0000-0000-000000000003';

    public function run(): void
    {
        $contacts = [
            [
                'id' => self::ALPHA_CONTACT_1_ID,
                'client_id' => ClientSeeder::CLIENT_ALPHA_ID,
                'name' => 'Sokha Phan',
                'email' => 'sokha.phan@alphatech.kh',
                'phone_number' => '+85512111001',
                'telegram_username' => 'sokha_phan',
                'telegram_chat_id' => '100000001',
                'position' => 'IT Manager',
                'is_primary_contact' => true,
                'is_active' => true,
            ],
            [
                'id' => self::ALPHA_CONTACT_2_ID,
                'client_id' => ClientSeeder::CLIENT_ALPHA_ID,
                'name' => 'Dara Meas',
                'email' => 'dara.meas@alphatech.kh',
                'phone_number' => '+85512111002',
                'telegram_username' => 'dara_meas',
                'telegram_chat_id' => '100000002',
                'position' => 'HR Coordinator',
                'is_primary_contact' => false,
                'is_active' => true,
            ],
            [
                'id' => self::BETA_CONTACT_1_ID,
                'client_id' => ClientSeeder::CLIENT_BETA_ID,
                'name' => 'Leakhena Chan',
                'email' => 'leakhena@betalogistics.com',
                'phone_number' => '+85512222001',
                'telegram_username' => 'leakhena_chan',
                'telegram_chat_id' => '100000003',
                'position' => 'Operations Director',
                'is_primary_contact' => true,
                'is_active' => true,
            ],
        ];

        foreach ($contacts as $data) {
            ClientContact::updateOrCreate(['id' => $data['id']], $data);
        }

        $this->command->info('Client contacts seeded: 3 contacts across 2 clients');
    }
}

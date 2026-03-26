<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientLinkAddressSeeder extends Seeder
{
    public function run(): void
    {
        $clients = Client::all();

        foreach ($clients as $client) {
            if (empty($client->link_address)) {
                $latitude = trim((string) ($client->headquarter_latitude ?? ''));
                $longitude = trim((string) ($client->headquarter_longitude ?? ''));

                if ($latitude !== '' && $longitude !== '') {
                    $client->link_address = 'https://www.google.com/maps/search/?api=1&query=' . $latitude . ',' . $longitude;
                    $client->save();
                    continue;
                }

                $address = trim((string) ($client->headquarter_address ?? ''));
                if ($address !== '') {
                    $client->link_address = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
                    $client->save();
                }
            }
        }

        $this->command->info('Client link addresses seeded successfully.');
    }
}

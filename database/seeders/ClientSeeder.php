<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    const CLIENT_ALPHA_ID = 'dddddddd-0000-0000-0000-000000000001';

    const CLIENT_BETA_ID = 'dddddddd-0000-0000-0000-000000000002';

    const CLIENT_GAMMA_ID = 'dddddddd-0000-0000-0000-000000000003';

    const CLIENT_DELTA_ID = 'dddddddd-0000-0000-0000-000000000004';

    const CLIENT_EPSILON_ID = 'dddddddd-0000-0000-0000-000000000005';

    const CLIENT_ZETA_ID = 'dddddddd-0000-0000-0000-000000000006';

    const CLIENT_ETA_ID = 'dddddddd-0000-0000-0000-000000000007';

    const CLIENT_THETA_ID = 'dddddddd-0000-0000-0000-000000000008';

    const CLIENT_IOTA_ID = 'dddddddd-0000-0000-0000-000000000009';

    const CLIENT_KAPPA_ID = 'dddddddd-0000-0000-0000-000000000010';

    public function run(): void
    {
        $saleIds = DemoUserSeeder::SALE_USER_IDS;

        $this->createClient(
            self::CLIENT_ALPHA_ID,
            'CLT-0001',
            'REG-KH-2024-001',
            'Alpha Tech Solutions',
            '+85523456789',
            'contact@alphatech.kh',
            'Street 271, Sangkat Toul Tum Poung, Khan Chamkarmon, Phnom Penh',
            "11.5415295",
            "104.8920684",
            ['facebook' => 'https://facebook.com/alphatech', 'linkedin' => 'https://linkedin.com/company/alphatech', 'telegram' => 'https://t.me/alphatech'],
            true,
            $saleIds[0]
        );

        $this->createClient(
            self::CLIENT_BETA_ID,
            'CLT-0002',
            'REG-KH-2024-002',
            'Beta Logistics Group',
            '+85512987654',
            'info@betalogistics.com',
            'National Road 6A, Russei Keo, Phnom Penh',
            "11.543943",
            "104.9027925",
            ['facebook' => 'https://facebook.com/betalogistics', 'telegram' => 'https://t.me/betalogistics'],
            true,
            $saleIds[1]
        );

        $this->createClient(
            self::CLIENT_GAMMA_ID,
            'CLT-0003',
            'REG-KH-2024-003',
            'Gamma Corp',
            '+85511223344',
            'contact@gammacorp.com',
            'Street 123, Phnom Penh',
            "11.5408561",
            "104.908013",
            ['facebook' => 'https://facebook.com/gammacorp'],
            true,
            $saleIds[2]
        );

        $this->createClient(
            self::CLIENT_DELTA_ID,
            'CLT-0004',
            'REG-KH-2024-004',
            'Delta Innovations',
            '+85522334455',
            'info@deltainnovations.com',
            'Street 456, Phnom Penh',
            "11.5349475",
            "104.9094879",
            ['facebook' => 'https://facebook.com/deltainnovations'],
            true,
            $saleIds[3]
        );

        $this->createClient(
            self::CLIENT_EPSILON_ID,
            'CLT-0005',
            'REG-KH-2024-005',
            'Epsilon Systems',
            '+85533445566',
            'contact@epsilonsystems.com',
            'Street 789, Phnom Penh',
            "11.5349802",
            "104.9121787",
            ['facebook' => 'https://facebook.com/epsilonsystems'],
            true,
            $saleIds[4]
        );

        $this->createClient(
            self::CLIENT_ZETA_ID,
            'CLT-0006',
            'REG-KH-2024-006',
            'Zeta Solutions',
            '+85544556677',
            'info@zetasolutions.com',
            'Street 101, Phnom Penh',
            "11.5355859",
            "104.9246927",
            ['facebook' => 'https://facebook.com/zetasolutions'],
            true,
            $saleIds[0]
        );

        $this->createClient(
            self::CLIENT_ETA_ID,
            'CLT-0007',
            'REG-KH-2024-007',
            'Eta Technologies',
            '+85555667788',
            'contact@etatech.com',
            'Street 202, Phnom Penh',
            "11.5303658",
            "104.9229574",
            ['facebook' => 'https://facebook.com/etatech'],
            true,
            $saleIds[1]
        );

        $this->createClient(
            self::CLIENT_THETA_ID,
            'CLT-0008',
            'REG-KH-2024-008',
            'Theta Dynamics',
            '+85566778899',
            'info@thetadynamics.com',
            'Street 303, Phnom Penh',
            "11.5438039",
            "104.8299792",
            ['facebook' => 'https://facebook.com/thetadynamics'],
            true,
            $saleIds[2]
        );

        $this->createClient(
            self::CLIENT_IOTA_ID,
            'CLT-0009',
            'REG-KH-2024-009',
            'Iota Enterprises',
            '+85577889900',
            'contact@iotaenterprises.com',
            'Street 404, Phnom Penh',
            "11.5354093",
            "104.922258",
            ['facebook' => 'https://facebook.com/iotaenterprises'],
            true,
            $saleIds[3]
        );

        $this->createClient(
            self::CLIENT_KAPPA_ID,
            'CLT-0010',
            'REG-KH-2024-010',
            'Kappa Industries',
            '+85588990011',
            'info@kappaindustries.com',
            'Street 505, Phnom Penh',
            "11.5342085",
            "104.9305614",
            ['facebook' => 'https://facebook.com/kappaindustries'],
            true,
            $saleIds[4]
        );

        // Populate PostGIS location column from lat/lng
        DB::statement("
            UPDATE clients
            SET location = ST_SetSRID(ST_MakePoint(
                CAST(headquarter_longitude AS double precision),
                CAST(headquarter_latitude AS double precision)
            ), 4326)
            WHERE headquarter_latitude IS NOT NULL
              AND headquarter_longitude IS NOT NULL
        ");

        $this->command->info('All 10 clients have been seeded successfully across 5 sale users.');
    }

    private function createClient(
        string $id,
        string $code,
        string $company_code,
        string $company_name,
        string $phone_number,
        string $email,
        string $headquarter_address,
        string $headquarter_latitude,
        string $headquarter_longitude,
        array $social_links,
        bool $is_active,
        string $assigned_sale_id
    ): void {
        Client::updateOrCreate(
            ['id' => $id],
            [
                'code' => $code,
                'company_code' => $company_code,
                'company_name' => $company_name,
                'phone_number' => $phone_number,
                'email' => $email,
                'headquarter_address' => $headquarter_address,
                'headquarter_latitude' => $headquarter_latitude,
                'headquarter_longitude' => $headquarter_longitude,
                'social_links' => json_encode($social_links),
                'is_active' => $is_active,
                'assigned_sale_id' => $assigned_sale_id,
                'geofence_radius' => 200,
            ]
        );
    }
}

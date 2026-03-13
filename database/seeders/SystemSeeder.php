<?php

namespace Database\Seeders;

use App\Models\System;
use Illuminate\Database\Seeder;

class SystemSeeder extends Seeder
{
    // Static UUIDs — used across seeders for cross-referencing
    const COMS_ID = 'aaaaaaaa-0000-0000-0000-000000000001';

    const CRM_ID = 'aaaaaaaa-0000-0000-0000-000000000002';

    const HRMS_ID = 'aaaaaaaa-0000-0000-0000-000000000003';

    public function run(): void
    {
        $systems = [
            [
                'id' => self::COMS_ID,
                'code' => 'SYS-COMS_V1',
                'name' => 'coms',
                'description' => 'Customer Onboarding Management System',
                'is_active' => true,
            ],
            [
                'id' => self::CRM_ID,
                'code' => 'SYS-CRM_V1',
                'name' => 'crm',
                'description' => 'Customer Relationship Management',
                'is_active' => true,
            ],
            [
                'id' => self::HRMS_ID,
                'code' => 'SYS-HRMS_V1',
                'name' => 'hrms',
                'description' => 'Human Resource Management System',
                'is_active' => true,
            ],
        ];

        foreach ($systems as $data) {
            System::updateOrCreate(['name' => $data['name']], $data);
        }

        $this->command->info('Systems seeded: coms, crm, hrms');
    }
}

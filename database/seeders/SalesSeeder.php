<?php

namespace Database\Seeders;

use App\Models\Sale;
use Illuminate\Database\Seeder;

class SalesSeeder extends Seeder
{
    private const SALES = [
        [
            'id' => 'cccccccc-0000-0000-0000-000000000001',
            'sale_order_code' => 'SO-20260413-0001',
            'client_id' => ClientSeeder::CLIENT_ALPHA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[0],
            'content' => [
                'products' => [
                    ['name' => 'CheckinMe Pro', 'price' => 1200, 'quantity' => 1],
                ],
                'price' => 1200,
                'quantity' => 1,
                'discount_amount' => 100,
                'amount' => 1100,
                'acc_created_at' => '2026-04-13T07:28:00Z',
                'acc_expired_at' => '2027-04-13T07:28:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000002',
            'sale_order_code' => 'SO-20260413-0002',
            'client_id' => ClientSeeder::CLIENT_BETA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[1],
            'content' => [
                'products' => [
                    ['name' => 'Attendance Module', 'price' => 800, 'quantity' => 1],
                    ['name' => 'SMS Notification Pack', 'price' => 200, 'quantity' => 1],
                ],
                'price' => 1000,
                'quantity' => 2,
                'discount_amount' => 50,
                'amount' => 950,
                'acc_created_at' => '2026-04-13T08:00:00Z',
                'acc_expired_at' => '2027-04-13T08:00:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000003',
            'sale_order_code' => 'SO-20260413-0003',
            'client_id' => ClientSeeder::CLIENT_GAMMA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[2],
            'content' => [
                'products' => [
                    ['name' => 'Payroll Starter', 'price' => 1500, 'quantity' => 1],
                ],
                'price' => 1500,
                'quantity' => 1,
                'discount_amount' => 0,
                'amount' => 1500,
                'acc_created_at' => '2026-04-13T08:10:00Z',
                'acc_expired_at' => '2027-04-13T08:10:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000004',
            'sale_order_code' => 'SO-20260413-0004',
            'client_id' => ClientSeeder::CLIENT_DELTA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[3],
            'content' => [
                'products' => [
                    ['name' => 'CRM Connector', 'price' => 600, 'quantity' => 1],
                    ['name' => 'Training Hours', 'price' => 300, 'quantity' => 2],
                ],
                'price' => 1200,
                'quantity' => 3,
                'discount_amount' => 120,
                'amount' => 1080,
                'acc_created_at' => '2026-04-13T08:20:00Z',
                'acc_expired_at' => '2027-04-13T08:20:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000005',
            'sale_order_code' => 'SO-20260413-0005',
            'client_id' => ClientSeeder::CLIENT_EPSILON_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[4],
            'content' => [
                'products' => [
                    ['name' => 'Mobile Check-in', 'price' => 900, 'quantity' => 1],
                ],
                'price' => 900,
                'quantity' => 1,
                'discount_amount' => 90,
                'amount' => 810,
                'acc_created_at' => '2026-04-13T08:30:00Z',
                'acc_expired_at' => '2027-04-13T08:30:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000006',
            'sale_order_code' => 'SO-20260413-0006',
            'client_id' => ClientSeeder::CLIENT_ZETA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[0],
            'content' => [
                'products' => [
                    ['name' => 'CheckinMe Pro', 'price' => 1200, 'quantity' => 1],
                    ['name' => 'VAT Setup', 'price' => 150, 'quantity' => 1],
                ],
                'price' => 1350,
                'quantity' => 2,
                'discount_amount' => 150,
                'amount' => 1200,
                'acc_created_at' => '2026-04-13T08:40:00Z',
                'acc_expired_at' => '2027-04-13T08:40:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000007',
            'sale_order_code' => 'SO-20260413-0007',
            'client_id' => ClientSeeder::CLIENT_ETA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[1],
            'content' => [
                'products' => [
                    ['name' => 'Branch Access', 'price' => 700, 'quantity' => 2],
                ],
                'price' => 1400,
                'quantity' => 2,
                'discount_amount' => 140,
                'amount' => 1260,
                'acc_created_at' => '2026-04-13T08:50:00Z',
                'acc_expired_at' => '2027-04-13T08:50:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000008',
            'sale_order_code' => 'SO-20260413-0008',
            'client_id' => ClientSeeder::CLIENT_THETA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[2],
            'content' => [
                'products' => [
                    ['name' => 'HR Starter', 'price' => 1000, 'quantity' => 1],
                ],
                'price' => 1000,
                'quantity' => 1,
                'discount_amount' => 0,
                'amount' => 1000,
                'acc_created_at' => '2026-04-13T09:00:00Z',
                'acc_expired_at' => '2027-04-13T09:00:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000009',
            'sale_order_code' => 'SO-20260413-0009',
            'client_id' => ClientSeeder::CLIENT_IOTA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[3],
            'content' => [
                'products' => [
                    ['name' => 'Attendance Module', 'price' => 800, 'quantity' => 1],
                    ['name' => 'Training Support', 'price' => 250, 'quantity' => 1],
                ],
                'price' => 1050,
                'quantity' => 2,
                'discount_amount' => 50,
                'amount' => 1000,
                'acc_created_at' => '2026-04-13T09:10:00Z',
                'acc_expired_at' => '2027-04-13T09:10:00Z',
                'vat' => 0.10,
            ],
        ],
        [
            'id' => 'cccccccc-0000-0000-0000-000000000010',
            'sale_order_code' => 'SO-20260413-0010',
            'client_id' => ClientSeeder::CLIENT_KAPPA_ID,
            'created_by' => DemoUserSeeder::SALE_USER_IDS[4],
            'content' => [
                'products' => [
                    ['name' => 'Enterprise Suite', 'price' => 2000, 'quantity' => 1],
                ],
                'price' => 2000,
                'quantity' => 1,
                'discount_amount' => 200,
                'amount' => 1800,
                'acc_created_at' => '2026-04-13T09:20:00Z',
                'acc_expired_at' => '2027-04-13T09:20:00Z',
                'vat' => 0.10,
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::SALES as $saleData) {
            Sale::updateOrCreate(
                ['id' => $saleData['id']],
                [
                    'sale_order_code' => $saleData['sale_order_code'],
                    'client_id' => $saleData['client_id'],
                    'created_by' => $saleData['created_by'],
                    'content' => $saleData['content'],
                ]
            );
        }

        $this->command->info('Sales seeded: 10 client sales across 5 sale users.');
    }
}

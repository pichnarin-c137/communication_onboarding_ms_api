<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\User;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Headquarters',
                'address' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'Anystate',
                'postal_code' => '12345',
                'country' => 'Cambodia',
                'headquarters_lat' => '11.5226472',
                'headquarters_lng' => '104.9003794',
            ],
            [
                'name' => 'Branch Office 1',
                'address' => '456 Elm St',
                'city' => 'Othertown',
                'state' => 'Otherstate',
                'postal_code' => '67890',
                'country' => 'Cambodia',
                'headquarters_lat' => '11.5226472',
                'headquarters_lng' => '104.887565',
            ],
            [
                'name' => 'Branch Office 2',
                'address' => '789 Oak St',
                'city' => 'Sometown',
                'state' => 'Somestate',
                'postal_code' => '54321',
                'country' => 'Cambodia',
                'headquarters_lat' => '11.525958',
                'headquarters_lng' => '104.882739',
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(['name' => $branch['name']], $branch);
        }

        $headquartersId = Branch::where('name', 'Headquarters')->value('id');
        if ($headquartersId !== null) {
            User::whereNull('branch_id')->update(['branch_id' => $headquartersId]);
        }

        $this->command->info('Branches seeded: 3 branches created');
    }
}

<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = [
            ['name_en' => 'Retail Store', 'name_km' => 'ហាងលក់រាយ'],
            ['name_en' => 'Wholesale Business', 'name_km' => 'អាជីវកម្មលក់ដុំ'],
            ['name_en' => 'Convenience Store', 'name_km' => 'ហាងងាយស្រួល'],
            ['name_en' => 'Supermarket', 'name_km' => 'ផ្សារទំនើប'],
            ['name_en' => 'Restaurant', 'name_km' => 'ភោជនីយដ្ឋាន'],
            ['name_en' => 'Cafe / Coffee Shop', 'name_km' => 'ហាងកាហ្វេ'],
            ['name_en' => 'Street Food Vendor', 'name_km' => 'អ្នកលក់អាហារតាមផ្លូវ'],
            ['name_en' => 'Bakery', 'name_km' => 'ហាងនំប៉័ង'],
            ['name_en' => 'Hotel', 'name_km' => 'សណ្ឋាគារ'],
            ['name_en' => 'Guesthouse', 'name_km' => 'ផ្ទះសំណាក់'],
            ['name_en' => 'Travel Agency', 'name_km' => 'ក្រុមហ៊ុនទេសចរណ៍'],
            ['name_en' => 'Tour Guide Service', 'name_km' => 'សេវាកម្មមគ្គុទេសក៍ទេសចរណ៍'],
            ['name_en' => 'Software Company', 'name_km' => 'ក្រុមហ៊ុនកម្មវិធី'],
            ['name_en' => 'IT Services', 'name_km' => 'សេវាកម្មព័ត៌មានវិទ្យា'],
            ['name_en' => 'Digital Marketing Agency', 'name_km' => 'ក្រុមហ៊ុនទីផ្សារឌីជីថល'],
            ['name_en' => 'Telecommunications Company', 'name_km' => 'ក្រុមហ៊ុនទូរគមនាគមន៍'],
            ['name_en' => 'Factory / Manufacturing', 'name_km' => 'រោងចក្រ'],
            ['name_en' => 'Construction Company', 'name_km' => 'ក្រុមហ៊ុនសំណង់'],
            ['name_en' => 'Garment Factory', 'name_km' => 'រោងចក្រកាត់ដេរ'],
            ['name_en' => 'Delivery Service', 'name_km' => 'សេវាដឹកជញ្ជូន'],
            ['name_en' => 'Transportation Company', 'name_km' => 'ក្រុមហ៊ុនដឹកជញ្ជូន'],
            ['name_en' => 'Logistics Company', 'name_km' => 'ក្រុមហ៊ុនឡូជីស្ទិច'],
            ['name_en' => 'Bank', 'name_km' => 'ធនាគារ'],
            ['name_en' => 'Microfinance Institution', 'name_km' => 'ស្ថាប័នហិរញ្ញវត្ថុខ្នាតតូច'],
            ['name_en' => 'Accounting Firm', 'name_km' => 'ក្រុមហ៊ុនគណនេយ្យ'],
            ['name_en' => 'Law Firm', 'name_km' => 'ក្រុមហ៊ុនច្បាប់'],
            ['name_en' => 'School', 'name_km' => 'សាលារៀន'],
            ['name_en' => 'University', 'name_km' => 'សាកលវិទ្យាល័យ'],
            ['name_en' => 'Training Center', 'name_km' => 'មជ្ឈមណ្ឌលបណ្តុះបណ្តាល'],
            ['name_en' => 'Language School', 'name_km' => 'សាលាភាសា'],
        ];

        foreach ($businessTypes as $businessType) {
            BusinessType::firstOrCreate(
                ['name_en' => $businessType['name_en']],
                [
                    'id' => (string) Str::uuid(),
                    'name_km' => $businessType['name_km'],
                ]
            );
        }
    }
}

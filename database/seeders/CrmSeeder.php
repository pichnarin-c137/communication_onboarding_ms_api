<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $saleIds = DemoUserSeeder::SALE_USER_IDS;

        $businessTypeIds = BusinessType::pluck('id', 'name_en');
        $btRetail = $businessTypeIds['Retail Store'] ?? null;
        $btSoftware = $businessTypeIds['Software Company'] ?? null;
        $btRestaurant = $businessTypeIds['Restaurant'] ?? null;
        $btLogistics = $businessTypeIds['Logistics Company'] ?? null;
        $btHotel = $businessTypeIds['Hotel'] ?? null;
        $btSchool = $businessTypeIds['School'] ?? null;
        $btFactory = $businessTypeIds['Factory / Manufacturing'] ?? null;
        $btBank = $businessTypeIds['Bank'] ?? null;

        // ─── Prospect contacts (no deals yet) ───────────────────────────────
        $this->seedContact('eeeeeeee-0000-0000-0000-000000000001', [
            'company_name' => 'Lotus Bistro',
            'company_name_kh' => 'ហាងបាយឡូទាស់',
            'contact_name' => 'Sok Chanthou',
            'phone' => '+85512111001',
            'email' => 'chanthou@lotusbistro.kh',
            'address' => 'Street 240, Phnom Penh',
            'business_type_id' => $btRestaurant,
            'source' => 'cold_call',
            'notes' => 'Interested in a 5-seat POS plan; follow up next week.',
            'status' => 'prospect',
            'created_by' => $saleIds[0],
        ]);

        $this->seedContact('eeeeeeee-0000-0000-0000-000000000002', [
            'company_name' => 'Khmer Crafts Co.',
            'contact_name' => 'Lim Phalla',
            'phone' => '+85512111002',
            'email' => 'phalla@khmercrafts.com',
            'address' => 'Street 178, Phnom Penh',
            'business_type_id' => $btRetail,
            'source' => 'website',
            'status' => 'prospect',
            'created_by' => $saleIds[1],
        ]);

        $this->seedContact('eeeeeeee-0000-0000-0000-000000000003', [
            'company_name' => 'Mekong Stay Hotel',
            'contact_name' => 'Vorn Sreyleak',
            'phone' => '+85512111003',
            'email' => 'sreyleak@mekongstay.com',
            'business_type_id' => $btHotel,
            'source' => 'referral',
            'notes' => 'Referred by Beta Logistics; needs multi-property setup.',
            'status' => 'prospect',
            'created_by' => $saleIds[2],
        ]);

        $this->seedContact('eeeeeeee-0000-0000-0000-000000000004', [
            'company_name' => 'Bright Future Academy',
            'contact_name' => 'Mao Visal',
            'phone' => '+85512111004',
            'email' => 'visal@bfacademy.edu.kh',
            'business_type_id' => $btSchool,
            'source' => 'event',
            'status' => 'prospect',
            'created_by' => $saleIds[3],
        ]);

        // ─── Contacts with active deals ─────────────────────────────────────
        $contactAlpha = $this->seedContact('eeeeeeee-0000-0000-0000-000000000005', [
            'company_name' => 'Rainbow Garments',
            'contact_name' => 'Heng Sopheaktra',
            'phone' => '+85512111005',
            'email' => 'sopheaktra@rainbowgarments.kh',
            'address' => 'Phnom Penh Special Economic Zone',
            'business_type_id' => $btFactory,
            'source' => 'referral',
            'status' => 'deal_active',
            'created_by' => $saleIds[4],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000001', [
            'crm_contact_id' => $contactAlpha->id,
            'title' => 'Rainbow Garments — HR onboarding suite',
            'stage' => 'demo_scheduled',
            'value' => 4500,
            'expected_close_date' => Carbon::now()->addDays(14)->toDateString(),
            'notes' => 'Demo scheduled for next Tuesday at HQ.',
            'assigned_to' => $saleIds[4],
            'created_by' => $saleIds[4],
        ]);

        $contactBravo = $this->seedContact('eeeeeeee-0000-0000-0000-000000000006', [
            'company_name' => 'Silver Coffee Roasters',
            'contact_name' => 'Pich Davy',
            'phone' => '+85512111006',
            'email' => 'davy@silvercoffee.kh',
            'business_type_id' => $businessTypeIds['Cafe / Coffee Shop'] ?? null,
            'source' => 'website',
            'status' => 'deal_active',
            'created_by' => $saleIds[0],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000002', [
            'crm_contact_id' => $contactBravo->id,
            'title' => 'Silver Coffee — multi-branch POS',
            'stage' => 'proposal_sent',
            'value' => 8200,
            'expected_close_date' => Carbon::now()->addDays(21)->toDateString(),
            'notes' => 'Proposal sent; awaiting board approval.',
            'assigned_to' => $saleIds[0],
            'created_by' => $saleIds[0],
        ]);

        $contactCharlie = $this->seedContact('eeeeeeee-0000-0000-0000-000000000007', [
            'company_name' => 'Angkor Bank Microfinance',
            'contact_name' => 'Ouk Vannarith',
            'phone' => '+85512111007',
            'email' => 'vannarith@angkorbankmf.kh',
            'business_type_id' => $btBank,
            'source' => 'event',
            'status' => 'deal_active',
            'created_by' => $saleIds[1],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000003', [
            'crm_contact_id' => $contactCharlie->id,
            'title' => 'Angkor MF — staff training package',
            'stage' => 'negotiating',
            'value' => 12500,
            'expected_close_date' => Carbon::now()->addDays(7)->toDateString(),
            'notes' => 'Negotiating final discount and rollout schedule.',
            'assigned_to' => $saleIds[1],
            'created_by' => $saleIds[1],
        ]);

        // Second open deal on same contact
        $this->seedDeal('ffffffff-0000-0000-0000-000000000004', [
            'crm_contact_id' => $contactCharlie->id,
            'title' => 'Angkor MF — branch expansion add-on',
            'stage' => 'prospect',
            'value' => 3000,
            'assigned_to' => $saleIds[1],
            'created_by' => $saleIds[1],
        ]);

        $contactDelta = $this->seedContact('eeeeeeee-0000-0000-0000-000000000008', [
            'company_name' => 'Tonle Cargo Express',
            'contact_name' => 'Chea Bunheng',
            'phone' => '+85512111008',
            'email' => 'bunheng@tonlecargo.com',
            'business_type_id' => $btLogistics,
            'source' => 'cold_call',
            'status' => 'deal_active',
            'created_by' => $saleIds[2],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000005', [
            'crm_contact_id' => $contactDelta->id,
            'title' => 'Tonle Cargo — driver attendance pilot',
            'stage' => 'prospect',
            'value' => 2200,
            'assigned_to' => $saleIds[2],
            'created_by' => $saleIds[2],
        ]);

        // ─── Won contacts (link back to existing seeded clients via dedupe) ─
        $this->seedContact('eeeeeeee-0000-0000-0000-000000000009', [
            'company_name' => 'Alpha Tech Solutions',
            'contact_name' => 'Sambath Pisey',
            'phone' => '+85523456789',
            'email' => 'pisey@alphatech.kh',
            'business_type_id' => $btSoftware,
            'source' => 'referral',
            'notes' => 'Closed during Q1; original CRM record kept for history.',
            'status' => 'won',
            'synced_client_id' => ClientSeeder::CLIENT_ALPHA_ID,
            'created_by' => $saleIds[0],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000006', [
            'crm_contact_id' => 'eeeeeeee-0000-0000-0000-000000000009',
            'title' => 'Alpha Tech — initial COMS rollout',
            'stage' => 'won',
            'value' => 15000,
            'expected_close_date' => Carbon::now()->subDays(20)->toDateString(),
            'notes' => 'Signed contract; onboarding kicked off.',
            'assigned_to' => $saleIds[0],
            'client_id' => ClientSeeder::CLIENT_ALPHA_ID,
            'won_at' => Carbon::now()->subDays(20),
            'created_by' => $saleIds[0],
        ]);

        $this->seedContact('eeeeeeee-0000-0000-0000-000000000010', [
            'company_name' => 'Beta Logistics Group',
            'contact_name' => 'Ros Sokleng',
            'phone' => '+85512987654',
            'email' => 'sokleng@betalogistics.com',
            'business_type_id' => $btLogistics,
            'source' => 'event',
            'status' => 'won',
            'synced_client_id' => ClientSeeder::CLIENT_BETA_ID,
            'created_by' => $saleIds[1],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000007', [
            'crm_contact_id' => 'eeeeeeee-0000-0000-0000-000000000010',
            'title' => 'Beta Logistics — fleet driver training',
            'stage' => 'won',
            'value' => 9800,
            'expected_close_date' => Carbon::now()->subDays(40)->toDateString(),
            'assigned_to' => $saleIds[1],
            'client_id' => ClientSeeder::CLIENT_BETA_ID,
            'won_at' => Carbon::now()->subDays(40),
            'created_by' => $saleIds[1],
        ]);

        $this->seedContact('eeeeeeee-0000-0000-0000-000000000011', [
            'company_name' => 'Gamma Corp',
            'contact_name' => 'Nuon Channary',
            'phone' => '+85511223344',
            'email' => 'channary@gammacorp.com',
            'business_type_id' => $btRetail,
            'source' => 'website',
            'status' => 'won',
            'synced_client_id' => ClientSeeder::CLIENT_GAMMA_ID,
            'created_by' => $saleIds[2],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000008', [
            'crm_contact_id' => 'eeeeeeee-0000-0000-0000-000000000011',
            'title' => 'Gamma Corp — staff scheduling module',
            'stage' => 'won',
            'value' => 6400,
            'assigned_to' => $saleIds[2],
            'client_id' => ClientSeeder::CLIENT_GAMMA_ID,
            'won_at' => Carbon::now()->subDays(10),
            'created_by' => $saleIds[2],
        ]);

        // Cross-sell: a second open deal on the already-won Alpha contact
        $this->seedDeal('ffffffff-0000-0000-0000-000000000009', [
            'crm_contact_id' => 'eeeeeeee-0000-0000-0000-000000000009',
            'title' => 'Alpha Tech — advanced analytics add-on',
            'stage' => 'proposal_sent',
            'value' => 4200,
            'expected_close_date' => Carbon::now()->addDays(30)->toDateString(),
            'notes' => 'Expansion deal; same buyer.',
            'assigned_to' => $saleIds[0],
            'created_by' => $saleIds[0],
        ]);

        // ─── Lost contacts ──────────────────────────────────────────────────
        $contactLostOne = $this->seedContact('eeeeeeee-0000-0000-0000-000000000012', [
            'company_name' => 'Sunrise Mart',
            'contact_name' => 'Tep Sothy',
            'phone' => '+85512111012',
            'email' => 'sothy@sunrisemart.kh',
            'business_type_id' => $businessTypeIds['Convenience Store'] ?? null,
            'source' => 'cold_call',
            'status' => 'lost',
            'created_by' => $saleIds[3],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000010', [
            'crm_contact_id' => $contactLostOne->id,
            'title' => 'Sunrise Mart — POS pilot',
            'stage' => 'lost',
            'value' => 1800,
            'lost_at' => Carbon::now()->subDays(15),
            'lost_reason' => 'Chose competitor with lower upfront cost.',
            'assigned_to' => $saleIds[3],
            'created_by' => $saleIds[3],
        ]);

        $contactLostTwo = $this->seedContact('eeeeeeee-0000-0000-0000-000000000013', [
            'company_name' => 'Khmer Express Couriers',
            'contact_name' => 'Hak Vibol',
            'phone' => '+85512111013',
            'email' => 'vibol@khmerexpress.com',
            'business_type_id' => $btLogistics,
            'source' => 'other',
            'status' => 'lost',
            'created_by' => $saleIds[4],
        ]);

        $this->seedDeal('ffffffff-0000-0000-0000-000000000011', [
            'crm_contact_id' => $contactLostTwo->id,
            'title' => 'Khmer Express — driver tracking',
            'stage' => 'lost',
            'value' => 5600,
            'lost_at' => Carbon::now()->subDays(5),
            'lost_reason' => 'Budget frozen for the rest of the fiscal year.',
            'assigned_to' => $saleIds[4],
            'created_by' => $saleIds[4],
        ]);

        $this->command->info('CRM seeded: 13 contacts and 11 deals across all stages (3 won linked to existing clients).');
    }

    private function seedContact(string $id, array $attributes): CrmContact
    {
        return CrmContact::withTrashed()->updateOrCreate(
            ['id' => $id],
            $attributes + ['deleted_at' => null]
        );
    }

    private function seedDeal(string $id, array $attributes): CrmDeal
    {
        return CrmDeal::withTrashed()->updateOrCreate(
            ['id' => $id],
            $attributes + ['deleted_at' => null]
        );
    }
}

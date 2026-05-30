<?php

namespace Tests\Feature\Crm;

use App\Models\Client;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\User;
use Tests\TestCase;

class CrmModuleTest extends TestCase
{
    private User $sale;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sale = $this->createUser(['role' => 'sale']);
        $this->headers = $this->authHeadersFor($this->sale);
    }

    private function createContact(array $overrides = []): CrmContact
    {
        $response = $this->postJson('/api/v1/crm/contacts', array_merge([
            'company_name' => 'TechCorp Ltd',
            'contact_name' => 'John Doe',
            'phone' => '+855 12 345 678',
            'email' => 'john@techcorp.com',
        ], $overrides), $this->headers);

        $response->assertCreated();

        return CrmContact::findOrFail($response->json('data.id'));
    }

    /** @test */
    public function it_creates_a_contact_with_default_prospect_status(): void
    {
        $response = $this->postJson('/api/v1/crm/contacts', [
            'company_name' => 'TechCorp Ltd',
            'contact_name' => 'John Doe',
            'phone' => '+855 12 345 678',
        ], $this->headers);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'prospect')
            ->assertJsonPath('data.active_deals_count', 0);

        $this->assertDatabaseHas('crm_contacts', [
            'company_name' => 'TechCorp Ltd',
            'status' => 'prospect',
            'created_by' => $this->sale->id,
        ]);
    }

    /** @test */
    public function it_validates_required_contact_fields(): void
    {
        $this->postJson('/api/v1/crm/contacts', ['company_name' => 'X'], $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['contact_name', 'phone']);
    }

    /** @test */
    public function it_lists_contacts_with_pagination_meta(): void
    {
        $this->createContact();

        $this->getJson('/api/v1/crm/contacts', $this->headers)
            ->assertOk()
            ->assertJsonStructure([
                'success', 'message',
                'data' => [['id', 'company_name', 'status', 'active_deals_count', 'business_type']],
                'meta' => ['total', 'per_page', 'current_page', 'last_page', 'from', 'to'],
            ]);
    }

    /** @test */
    public function it_soft_deletes_a_contact_and_excludes_it_from_the_list(): void
    {
        $contact = $this->createContact();

        $this->deleteJson("/api/v1/crm/contacts/{$contact->id}", [], $this->headers)
            ->assertOk()
            ->assertJsonPath('message', 'Contact deleted.');

        $this->assertSoftDeleted('crm_contacts', ['id' => $contact->id]);

        $list = $this->getJson('/api/v1/crm/contacts', $this->headers)->json('data');
        $this->assertEmpty($list);
    }

    /** @test */
    public function creating_a_deal_moves_the_contact_to_deal_active(): void
    {
        $contact = $this->createContact();

        $response = $this->postJson('/api/v1/crm/deals', [
            'contact_id' => $contact->id,
            'title' => 'TechCorp HR Suite',
            'value' => 5000,
        ], $this->headers);

        $response->assertCreated()
            ->assertJsonPath('data.stage', 'prospect')
            ->assertJsonPath('data.value', 5000);

        $this->assertDatabaseHas('crm_contacts', [
            'id' => $contact->id,
            'status' => 'deal_active',
        ]);

        // active_deals_count now reflects the open deal
        $this->getJson("/api/v1/crm/contacts/{$contact->id}", $this->headers)
            ->assertJsonPath('data.active_deals_count', 1);
    }

    /** @test */
    public function winning_a_deal_creates_a_client_and_syncs_to_the_dropdown(): void
    {
        $contact = $this->createContact();
        $deal = CrmDeal::create([
            'crm_contact_id' => $contact->id,
            'title' => 'TechCorp HR Suite',
            'stage' => 'negotiating',
            'value' => 5000,
            'assigned_to' => $this->sale->id,
        ]);

        $response = $this->postJson("/api/v1/crm/deals/{$deal->id}/won", [], $this->headers);

        $response->assertOk()
            ->assertJsonPath('message', 'Deal marked as won. Client synced to COMS.')
            ->assertJsonPath('data.client_name', 'TechCorp Ltd')
            ->assertJsonPath('data.deal_id', $deal->id);

        $clientId = $response->json('data.client_id');
        $this->assertNotNull($clientId);

        // Client row created and owned by the deal owner
        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'company_name' => 'TechCorp Ltd',
            'phone_number' => '+855 12 345 678',
            'assigned_sale_id' => $this->sale->id,
        ]);

        // Deal + contact updated
        $this->assertDatabaseHas('crm_deals', ['id' => $deal->id, 'stage' => 'won', 'client_id' => $clientId]);
        $this->assertDatabaseHas('crm_contacts', ['id' => $contact->id, 'status' => 'won', 'synced_client_id' => $clientId]);

        // Appears exactly once in the clients dropdown, tagged as CRM-originated
        $dropdown = $this->getJson('/api/v1/selection/clients-dropdown', $this->headers)->json('data');
        $matches = collect($dropdown)->where('id', $clientId);
        $this->assertCount(1, $matches);
        $this->assertTrue($matches->first()['from_crm']);
    }

    /** @test */
    public function the_dropdown_hides_inactive_clients_and_only_tags_crm_origin(): void
    {
        // Manually-created client: active, not from CRM.
        $manual = Client::factory()->create(['is_active' => true, 'assigned_sale_id' => $this->sale->id]);
        // Inactive client must never appear.
        $inactive = Client::factory()->create(['is_active' => false, 'assigned_sale_id' => $this->sale->id]);

        // CRM-originated client via a won deal.
        $contact = $this->createContact();
        $deal = CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'D', 'stage' => 'negotiating', 'assigned_to' => $this->sale->id]);
        $crmClientId = $this->postJson("/api/v1/crm/deals/{$deal->id}/won", [], $this->headers)->json('data.client_id');

        $dropdown = collect($this->getJson('/api/v1/selection/clients-dropdown', $this->headers)->json('data'));

        $this->assertNull($dropdown->firstWhere('id', $inactive->id), 'Inactive clients must be excluded.');
        $this->assertFalse($dropdown->firstWhere('id', $manual->id)['from_crm']);
        $this->assertTrue($dropdown->firstWhere('id', $crmClientId)['from_crm']);
    }

    /** @test */
    public function winning_a_second_deal_for_the_same_contact_reuses_the_client(): void
    {
        $contact = $this->createContact();

        $first = CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Deal 1', 'stage' => 'negotiating', 'assigned_to' => $this->sale->id]);
        $second = CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Deal 2', 'stage' => 'negotiating', 'assigned_to' => $this->sale->id]);

        $firstClientId = $this->postJson("/api/v1/crm/deals/{$first->id}/won", [], $this->headers)->json('data.client_id');
        $secondClientId = $this->postJson("/api/v1/crm/deals/{$second->id}/won", [], $this->headers)->json('data.client_id');

        $this->assertSame($firstClientId, $secondClientId);
        $this->assertSame(1, Client::count());
    }

    /** @test */
    public function losing_a_deal_records_the_reason(): void
    {
        $contact = $this->createContact();
        $deal = CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Deal', 'stage' => 'proposal_sent']);

        $this->postJson("/api/v1/crm/deals/{$deal->id}/lost", ['lost_reason' => 'Budget cut'], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.stage', 'lost')
            ->assertJsonPath('data.lost_reason', 'Budget cut');

        $this->assertDatabaseHas('crm_contacts', ['id' => $contact->id, 'status' => 'lost']);
    }

    /** @test */
    public function pipeline_stats_returns_all_six_stages_zero_filled(): void
    {
        $contact = $this->createContact();
        CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'A', 'stage' => 'prospect', 'value' => 1000, 'assigned_to' => $this->sale->id]);
        CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'B', 'stage' => 'prospect', 'value' => 500, 'assigned_to' => $this->sale->id]);

        $stages = $this->getJson('/api/v1/crm/pipeline/stats', $this->headers)
            ->assertOk()
            ->json('data.stages');

        $this->assertCount(6, $stages);
        $byStage = collect($stages)->keyBy('stage');
        $this->assertEquals(2, $byStage['prospect']['count']);
        $this->assertEquals(1500.0, $byStage['prospect']['total_value']);
        $this->assertEquals(0, $byStage['won']['count']);
        $this->assertEquals(0.0, $byStage['lost']['total_value']);
    }

    /** @test */
    public function a_terminal_deal_cannot_be_edited(): void
    {
        $contact = $this->createContact();
        $deal = CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Deal', 'stage' => 'negotiating', 'assigned_to' => $this->sale->id]);
        $this->postJson("/api/v1/crm/deals/{$deal->id}/won", [], $this->headers)->assertOk();

        $this->patchJson("/api/v1/crm/deals/{$deal->id}", ['title' => 'Renamed'], $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_DEAL_STAGE_TRANSITION');
    }

    /** @test */
    public function winning_an_already_closed_deal_returns_conflict(): void
    {
        $contact = $this->createContact();
        $deal = CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Deal', 'stage' => 'negotiating', 'assigned_to' => $this->sale->id]);
        $this->postJson("/api/v1/crm/deals/{$deal->id}/won", [], $this->headers)->assertOk();

        $this->postJson("/api/v1/crm/deals/{$deal->id}/won", [], $this->headers)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'DEAL_ALREADY_CLOSED');
    }

    /** @test */
    public function a_sale_only_sees_their_own_deals_and_pipeline(): void
    {
        $contact = $this->createContact();
        $otherSale = $this->createUser(['role' => 'sale']);

        $mine = CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Mine', 'stage' => 'prospect', 'value' => 1000, 'assigned_to' => $this->sale->id]);
        CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Theirs', 'stage' => 'prospect', 'value' => 9000, 'assigned_to' => $otherSale->id]);

        // Deal list: only my deal.
        $deals = $this->getJson('/api/v1/crm/deals', $this->headers)->assertOk()->json('data');
        $this->assertCount(1, $deals);
        $this->assertSame($mine->id, $deals[0]['id']);

        // Pipeline stats: only my value is counted.
        $stages = collect($this->getJson('/api/v1/crm/pipeline/stats', $this->headers)->json('data.stages'))->keyBy('stage');
        $this->assertEquals(1, $stages['prospect']['count']);
        $this->assertEquals(1000.0, $stages['prospect']['total_value']);
    }

    /** @test */
    public function an_admin_sees_every_sales_deals_and_pipeline(): void
    {
        $contact = $this->createContact();
        $otherSale = $this->createUser(['role' => 'sale']);
        $adminHeaders = $this->authHeadersFor($this->createAdmin());

        CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Mine', 'stage' => 'prospect', 'value' => 1000, 'assigned_to' => $this->sale->id]);
        CrmDeal::create(['crm_contact_id' => $contact->id, 'title' => 'Theirs', 'stage' => 'prospect', 'value' => 9000, 'assigned_to' => $otherSale->id]);

        $deals = $this->getJson('/api/v1/crm/deals', $adminHeaders)->assertOk()->json('data');
        $this->assertCount(2, $deals);

        $stages = collect($this->getJson('/api/v1/crm/pipeline/stats', $adminHeaders)->json('data.stages'))->keyBy('stage');
        $this->assertEquals(2, $stages['prospect']['count']);
        $this->assertEquals(10000.0, $stages['prospect']['total_value']);
    }

    /** @test */
    public function deal_and_contact_responses_expose_the_creator(): void
    {
        $createResp = $this->postJson('/api/v1/crm/contacts', [
            'company_name' => 'TechCorp Ltd',
            'contact_name' => 'John Doe',
            'phone' => '+855 12 345 678',
        ], $this->headers)->assertCreated();

        $createResp->assertJsonPath('data.created_by.id', $this->sale->id)
            ->assertJsonPath('data.created_by.name', trim("{$this->sale->first_name} {$this->sale->last_name}"));

        $contactId = $createResp->json('data.id');

        $dealResp = $this->postJson('/api/v1/crm/deals', [
            'contact_id' => $contactId,
            'title' => 'TechCorp HR Suite',
        ], $this->headers)->assertCreated();

        $dealResp->assertJsonPath('data.created_by.id', $this->sale->id)
            ->assertJsonPath('data.created_by.name', trim("{$this->sale->first_name} {$this->sale->last_name}"));
    }

    /** @test */
    public function crm_actions_are_written_to_the_activity_log(): void
    {
        $contact = $this->createContact();
        $this->postJson('/api/v1/crm/deals', [
            'contact_id' => $contact->id,
            'title' => 'TechCorp HR Suite',
        ], $this->headers)->assertCreated();

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->sale->id,
            'action' => 'crm_contact_created',
        ]);
        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->sale->id,
            'action' => 'crm_deal_created',
        ]);
    }

    /** @test */
    public function trainers_cannot_access_crm_endpoints(): void
    {
        $trainer = $this->createUser(['role' => 'trainer']);

        $this->getJson('/api/v1/crm/contacts', $this->authHeadersFor($trainer))
            ->assertStatus(403);
    }
}

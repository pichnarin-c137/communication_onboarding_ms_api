<?php

namespace Tests\Feature\Analytics;

use Tests\TestCase;

class AnalyticsScopeAndGuardsTest extends TestCase
{
    public function test_overview_requires_authentication(): void
    {
        $this->getJson('/api/v1/analytics/overview?from=2026-05-01&to=2026-05-22')
            ->assertStatus(401);
    }

    public function test_overview_returns_standard_envelope_for_admin(): void
    {
        $admin = $this->createAdmin();

        $response = $this->getJson('/api/v1/analytics/overview?from=2026-05-01&to=2026-05-22', $this->authHeadersFor($admin))
            ->assertOk()
            ->assertJsonStructure([
                'success', 'message', 'data' => ['kpis'],
                'meta' => ['generated_at', 'period' => ['from', 'to'], 'compare_period', 'scope' => ['role', 'user_id', 'scoped_trainer_ids']],
            ]);

        $this->assertSame('admin', $response->json('meta.scope.role'));
        $this->assertNull($response->json('meta.scope.scoped_trainer_ids'));
    }

    public function test_range_too_large_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->getJson('/api/v1/analytics/overview?from=2024-01-01&to=2026-05-22', $this->authHeadersFor($admin))
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'RANGE_TOO_LARGE');
    }

    public function test_invalid_date_format_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->getJson('/api/v1/analytics/overview?from=05-01-2026&to=2026-05-22', $this->authHeadersFor($admin))
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'INVALID_DATE_FORMAT');
    }

    public function test_invalid_date_order_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->getJson('/api/v1/analytics/overview?from=2026-05-22&to=2026-05-01', $this->authHeadersFor($admin))
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'INVALID_DATE_ORDER');
    }

    public function test_invalid_metric_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->getJson('/api/v1/analytics/trends?from=2026-05-01&to=2026-05-22&metric=bogus', $this->authHeadersFor($admin))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_METRIC');
    }

    public function test_trainer_cannot_access_sales_endpoint(): void
    {
        $trainer = $this->createUser(['role' => 'trainer']);

        // First the role middleware blocks at 403 via AdminOnlyException (existing project behavior).
        $this->getJson('/api/v1/analytics/sales?from=2026-05-01&to=2026-05-22', $this->authHeadersFor($trainer))
            ->assertStatus(403);
    }

    public function test_trainer_cannot_request_other_trainers_scorecard(): void
    {
        $trainer = $this->createUser(['role' => 'trainer']);
        $other = $this->createUser(['role' => 'trainer']);

        $this->getJson("/api/v1/analytics/trainers/{$other->id}?from=2026-05-01&to=2026-05-22", $this->authHeadersFor($trainer))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'FORBIDDEN_SCOPE');
    }
}

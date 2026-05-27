<?php

namespace Tests\Feature\Analytics;

use App\Models\Appointment;
use App\Models\AppointmentFeedback;
use App\Models\AppointmentFeedbackToken;
use App\Models\Client;
use App\Models\FeedbackRespondent;
use App\Models\OnboardingClientFeedback;
use App\Models\OnboardingRequest;
use App\Models\OnboardingTrainerAssignment;
use App\Models\SaleTrainerAssignment;
use Tests\TestCase;

class AnalyticsEndpointSmokeTest extends TestCase
{
    private string $from = '2026-04-01';
    private string $to = '2026-05-22';

    private function seedScenario(): array
    {
        $admin = $this->createAdmin();
        $sale = $this->createUser(['role' => 'sale']);
        $trainer = $this->createUser(['role' => 'trainer']);
        $client = Client::factory()->create(['assigned_sale_id' => $sale->id]);

        SaleTrainerAssignment::factory()->create([
            'sale_user_id' => $sale->id,
            'trainer_user_id' => $trainer->id,
        ]);

        // Mix of appointment outcomes during period
        Appointment::factory()->count(3)->onTime()->create([
            'trainer_id' => $trainer->id, 'creator_id' => $sale->id, 'client_id' => $client->id,
            'scheduled_date' => '2026-05-10',
        ]);
        Appointment::factory()->late()->create([
            'trainer_id' => $trainer->id, 'creator_id' => $sale->id, 'client_id' => $client->id,
            'scheduled_date' => '2026-05-11',
        ]);
        Appointment::factory()->cancelled()->create([
            'trainer_id' => $trainer->id, 'creator_id' => $sale->id, 'client_id' => $client->id,
            'scheduled_date' => '2026-05-12',
        ]);
        Appointment::factory()->noShow()->create([
            'trainer_id' => $trainer->id, 'creator_id' => $sale->id, 'client_id' => $client->id,
            'scheduled_date' => '2026-05-13',
        ]);
        Appointment::factory()->demo()->done()->create([
            'trainer_id' => $trainer->id, 'creator_id' => $sale->id, 'client_id' => $client->id,
            'scheduled_date' => '2026-05-09',
        ]);

        // Onboarding life-cycle
        $startedAppt = Appointment::factory()->training()->done()->create([
            'trainer_id' => $trainer->id, 'creator_id' => $sale->id, 'client_id' => $client->id,
            'scheduled_date' => '2026-05-05',
        ]);
        $onb = OnboardingRequest::factory()->completed()->create([
            'trainer_id' => $trainer->id, 'client_id' => $client->id, 'appointment_id' => $startedAppt->id,
            'completed_at' => now(),
        ]);
        OnboardingTrainerAssignment::factory()->create([
            'onboarding_id' => $onb->id, 'trainer_id' => $trainer->id,
        ]);

        // Feedback (one good onboarding, one low appointment)
        OnboardingClientFeedback::factory()->create([
            'onboarding_id' => $onb->id, 'rating' => 5, 'submitted_at' => now(),
        ]);

        return compact('admin', 'sale', 'trainer', 'client', 'onb');
    }

    public function test_overview_admin_returns_aggregated_kpis(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/overview?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $kpis = $resp->json('data.kpis');
        $this->assertArrayHasKey('appointments_total', $kpis);
        $this->assertArrayHasKey('completion_rate', $kpis);
        $this->assertGreaterThan(0, $kpis['appointments_total']['value']);
    }

    public function test_trends_returns_buckets_for_completion_rate(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/trends?from={$this->from}&to={$this->to}&metric=completion_rate", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertSame('completion_rate', $resp->json('data.metric'));
        $this->assertSame('week', $resp->json('data.group_by'));
        $this->assertIsArray($resp->json('data.series'));
    }

    public function test_appointments_breakdown_returns_status_and_location_pcts(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/appointments?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertArrayHasKey('by_status', $resp->json('data'));
        $this->assertArrayHasKey('done', $resp->json('data.by_status'));
        $this->assertArrayHasKey('demo_to_training_conversion', $resp->json('data'));
    }

    public function test_onboarding_funnel_returns_seven_stages_for_admin(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/onboarding-funnel?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $stages = $resp->json('data.stages');
        $this->assertCount(7, $stages);
        $keys = array_column($stages, 'key');
        $this->assertSame(['demo_created', 'demo_done', 'training_created', 'training_done', 'onboarding_started', 'onboarding_completed', 'feedback_submitted'], $keys);
    }

    public function test_onboarding_funnel_forbidden_for_trainer(): void
    {
        $trainer = $this->createUser(['role' => 'trainer']);

        $this->getJson("/api/v1/analytics/onboarding-funnel?from={$this->from}&to={$this->to}", $this->authHeadersFor($trainer))
            ->assertStatus(403);
    }

    public function test_satisfaction_returns_summary_distribution_trend(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/satisfaction?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertArrayHasKey('summary', $resp->json('data'));
        $this->assertArrayHasKey('distribution', $resp->json('data'));
        $this->assertArrayHasKey('trend', $resp->json('data'));
        $this->assertArrayHasKey('low_rating_alerts', $resp->json('data'));
    }

    public function test_trainers_leaderboard_for_admin(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/trainers?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertArrayHasKey('rows', $resp->json('data'));
        $this->assertArrayHasKey('meta', $resp->json('data'));
        $this->assertGreaterThan(0, count($resp->json('data.rows')));
    }

    public function test_trainer_scorecard_admin_can_view_any_trainer(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/trainers/{$s['trainer']->id}?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertSame($s['trainer']->id, $resp->json('data.trainer.id'));
        $this->assertArrayHasKey('kpis', $resp->json('data'));
    }

    public function test_trainer_self_scorecard(): void
    {
        $s = $this->seedScenario();

        $this->getJson("/api/v1/analytics/trainers/{$s['trainer']->id}?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['trainer']))
            ->assertOk()
            ->assertJsonPath('data.trainer.id', $s['trainer']->id);
    }

    public function test_sales_leaderboard_admin_only(): void
    {
        $s = $this->seedScenario();

        $this->getJson("/api/v1/analytics/sales?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk()
            ->assertJsonStructure(['data' => ['rows', 'meta']]);

        $this->getJson("/api/v1/analytics/sales?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['sale']))
            ->assertStatus(403);
    }

    public function test_heatmap_admin_only(): void
    {
        $s = $this->seedScenario();

        $this->getJson("/api/v1/analytics/heatmap?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk()
            ->assertJsonStructure(['data' => ['cells', 'max_count', 'total']]);

        $this->getJson("/api/v1/analytics/heatmap?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['sale']))
            ->assertStatus(403);
    }

    public function test_engagement_returns_telegram_and_lessons(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/engagement?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertArrayHasKey('telegram', $resp->json('data'));
        $this->assertArrayHasKey('lessons', $resp->json('data'));
    }

    public function test_onboardings_breakdown_returns_totals_rates_cycle(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/onboardings/breakdown?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertArrayHasKey('totals', $resp->json('data'));
        $this->assertArrayHasKey('rates', $resp->json('data'));
        $this->assertArrayHasKey('cycle_distribution', $resp->json('data'));
    }

    public function test_satisfaction_low_rating_onboarding_alert_resolves_trainer_name(): void
    {
        // Regression: low-rating onboarding feedback within the alert window must
        // resolve the trainer name (previously crashed in lowRatingAlerts()).
        $s = $this->seedScenario();

        $appt = Appointment::factory()->training()->done()->create([
            'trainer_id' => $s['trainer']->id, 'creator_id' => $s['sale']->id, 'client_id' => $s['client']->id,
            'scheduled_date' => '2026-05-18',
        ]);
        $onb = OnboardingRequest::factory()->completed()->create([
            'trainer_id' => $s['trainer']->id, 'client_id' => $s['client']->id, 'appointment_id' => $appt->id,
            'completed_at' => now(),
        ]);
        OnboardingTrainerAssignment::factory()->create([
            'onboarding_id' => $onb->id, 'trainer_id' => $s['trainer']->id,
        ]);
        OnboardingClientFeedback::factory()->low()->create([
            'onboarding_id' => $onb->id, 'rating' => 1, 'submitted_at' => now()->subDay(),
        ]);

        $resp = $this->getJson("/api/v1/analytics/satisfaction?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $alerts = collect($resp->json('data.low_rating_alerts'));
        $onbAlert = $alerts->firstWhere('source', 'onboarding');
        $this->assertNotNull($onbAlert, 'expected an onboarding low-rating alert');
        $this->assertSame($s['trainer']->id, $onbAlert['trainer_id']);
        $this->assertNotNull($onbAlert['trainer_name']);
    }

    public function test_sale_scope_returns_scoped_trainer_ids_in_meta(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/overview?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['sale']))
            ->assertOk();

        $this->assertSame('sale', $resp->json('meta.scope.role'));
        $this->assertContains($s['trainer']->id, $resp->json('meta.scope.scoped_trainer_ids'));
    }

    // ── Phase 4: Intelligence endpoints ──────────────────────────────────

    public function test_sentiment_aggregates_stored_scores_and_themes(): void
    {
        $s = $this->seedScenario();

        // A commented feedback inside the period — the FeedbackSentimentObserver
        // classifies it on create. Needs its own onboarding (feedback is unique
        // per onboarding_id) within the sale's scope.
        $appt = Appointment::factory()->training()->done()->create([
            'trainer_id' => $s['trainer']->id, 'creator_id' => $s['sale']->id, 'client_id' => $s['client']->id,
            'scheduled_date' => '2026-05-08',
        ]);
        $onb2 = OnboardingRequest::factory()->completed()->create([
            'trainer_id' => $s['trainer']->id, 'client_id' => $s['client']->id, 'appointment_id' => $appt->id,
            'completed_at' => '2026-05-10 10:00:00',
        ]);
        OnboardingClientFeedback::factory()->create([
            'onboarding_id' => $onb2->id,
            'rating' => 5, 'comment' => 'Excellent trainer, very clear and patient.',
            'submitted_at' => '2026-05-10 10:00:00',
        ]);

        $resp = $this->getJson("/api/v1/analytics/sentiment?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertArrayHasKey('summary', $resp->json('data'));
        $this->assertArrayHasKey('themes', $resp->json('data'));
        $this->assertArrayHasKey('representative', $resp->json('data'));
        $this->assertGreaterThanOrEqual(1, $resp->json('data.summary.analyzed_count'));
    }

    public function test_sentiment_forbidden_for_trainer(): void
    {
        $trainer = $this->createUser(['role' => 'trainer']);

        $this->getJson("/api/v1/analytics/sentiment?from={$this->from}&to={$this->to}", $this->authHeadersFor($trainer))
            ->assertStatus(403);
    }

    public function test_anomalies_returns_structure(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/anomalies?from={$this->from}&to={$this->to}", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertArrayHasKey('anomalies', $resp->json('data'));
        $this->assertIsArray($resp->json('data.anomalies'));
        $this->assertArrayHasKey('baseline_window', $resp->json('data'));
        $this->assertContains('cancellation_rate', $resp->json('data.metrics_monitored'));
    }

    public function test_anomalies_forbidden_for_trainer(): void
    {
        $trainer = $this->createUser(['role' => 'trainer']);

        $this->getJson("/api/v1/analytics/anomalies?from={$this->from}&to={$this->to}", $this->authHeadersFor($trainer))
            ->assertStatus(403);
    }

    public function test_cohorts_returns_completion_grid(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/cohorts?from={$this->from}&to={$this->to}&cohort_by=month", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertSame('month', $resp->json('data.cohort_by'));
        $this->assertSame('completion', $resp->json('data.metric'));
        $this->assertIsArray($resp->json('data.cohorts'));
    }

    public function test_cohorts_forbidden_for_trainer(): void
    {
        $trainer = $this->createUser(['role' => 'trainer']);

        $this->getJson("/api/v1/analytics/cohorts?from={$this->from}&to={$this->to}", $this->authHeadersFor($trainer))
            ->assertStatus(403);
    }

    public function test_forecast_returns_history_and_model(): void
    {
        $s = $this->seedScenario();

        $resp = $this->getJson("/api/v1/analytics/forecast?from={$this->from}&to={$this->to}&group_by=week&metric=onboardings_completed&horizon=4", $this->authHeadersFor($s['admin']))
            ->assertOk();

        $this->assertSame('onboardings_completed', $resp->json('data.metric'));
        $this->assertArrayHasKey('history', $resp->json('data'));
        $this->assertArrayHasKey('forecast', $resp->json('data'));
        $this->assertArrayHasKey('model', $resp->json('data'));
        $this->assertSame(4, $resp->json('data.horizon'));
    }

    public function test_forecast_rejects_unknown_metric(): void
    {
        $s = $this->seedScenario();

        $this->getJson("/api/v1/analytics/forecast?from={$this->from}&to={$this->to}&metric=bogus_metric", $this->authHeadersFor($s['admin']))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_METRIC');
    }

    public function test_forecast_forbidden_for_trainer(): void
    {
        $trainer = $this->createUser(['role' => 'trainer']);

        $this->getJson("/api/v1/analytics/forecast?from={$this->from}&to={$this->to}", $this->authHeadersFor($trainer))
            ->assertStatus(403);
    }
}

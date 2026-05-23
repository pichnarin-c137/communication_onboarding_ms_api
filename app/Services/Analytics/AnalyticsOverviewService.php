<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use App\Services\Analytics\Support\KpiBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsOverviewService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        $key = AnalyticsCacheKey::build('overview', $scope, $filters + [
            'from'    => $period->from->toDateString(),
            'to'      => $period->to->toDateString(),
            'compare' => $period->compareMode,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters) {
            $current = $this->snapshot($scope, $period->from, $period->to, $filters);
            $previous = ($period->compareFrom && $period->compareTo)
                ? $this->snapshot($scope, $period->compareFrom, $period->compareTo, $filters)
                : null;

            return ['kpis' => $this->buildKpis($scope, $current, $previous)];
        });
    }

    private function snapshot(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        return [
            'apt' => $this->appointmentAggregates($scope, $from, $to, $filters),
            'onb' => $this->onboardingAggregates($scope, $from, $to, $filters),
            'fb'  => $this->feedbackAggregates($scope, $from, $to, $filters),
        ];
    }

    private function appointmentAggregates(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $q = DB::table('appointments')
            ->whereNull('appointments.deleted_at')
            ->whereBetween('appointments.scheduled_date', [$from->toDateString(), $to->toDateString()]);

        $scope->applyAppointmentScope($q);
        AnalyticsFilters::applyAppointment($q, $filters);

        $threshold = (int) config('coms.analytics.on_time_threshold_min', 15);
        $row = (array) $q->selectRaw("
                count(*) as total,
                count(*) filter (where status = 'done') as done,
                count(*) filter (where status = 'cancelled') as cancelled,
                count(*) filter (
                    where status = 'done'
                      and actual_start_time is not null
                      and actual_start_time <= ((scheduled_date::timestamp + scheduled_start_time::time) + (interval '1 minute' * {$threshold}))
                ) as on_time_started
            ")->first();

        $total = (int) ($row['total'] ?? 0);
        $done = (int) ($row['done'] ?? 0);
        $cancelled = (int) ($row['cancelled'] ?? 0);
        $onTime = (int) ($row['on_time_started'] ?? 0);

        return [
            'total' => $total,
            'done' => $done,
            'cancelled' => $cancelled,
            'completion_rate'   => $total > 0 ? round($done / $total, 4) : 0.0,
            'cancellation_rate' => $total > 0 ? round($cancelled / $total, 4) : 0.0,
            'on_time_rate'      => $done > 0 ? round($onTime / $done, 4) : 0.0,
        ];
    }

    private function onboardingAggregates(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $q = DB::table('onboarding_requests')->whereNull('onboarding_requests.deleted_at');
        $scope->applyOnboardingScope($q);
        AnalyticsFilters::applyOnboarding($q, $filters);

        $active = (int) (clone $q)
            ->whereIn('status', ['in_progress', 'on_hold', 'revision_requested'])
            ->count();

        $completedQ = (clone $q)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from->utc(), $to->utc()]);

        $completed = (int) (clone $completedQ)->count();

        $avgRow = (array) (clone $completedQ)
            ->selectRaw('avg(extract(epoch from (completed_at - created_at)) / 3600.0) as h')
            ->first();
        $avgH = isset($avgRow['h']) && $avgRow['h'] !== null ? round((float) $avgRow['h'], 1) : null;

        return [
            'active'         => $active,
            'completed'      => $completed,
            'avg_duration_h' => $avgH,
        ];
    }

    private function feedbackAggregates(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        // Scoped onboarding ids (for OnboardingClientFeedback filter)
        $onbQ = DB::table('onboarding_requests')->whereNull('deleted_at');
        $scope->applyOnboardingScope($onbQ);
        AnalyticsFilters::applyOnboarding($onbQ, $filters);
        $onbIds = $onbQ->pluck('id')->all();

        // Scoped appointment ids (for AppointmentFeedback filter)
        $aptQ = DB::table('appointments')
            ->whereNull('deleted_at')
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()]);
        $scope->applyAppointmentScope($aptQ);
        AnalyticsFilters::applyAppointment($aptQ, $filters);
        $aptIds = $aptQ->pluck('id')->all();

        $onboardingFb = DB::table('onboarding_client_feedbacks')
            ->whereBetween('submitted_at', [$from->utc(), $to->utc()])
            ->when(! empty($onbIds), fn ($q) => $q->whereIn('onboarding_id', $onbIds))
            ->when(empty($onbIds) && ! $scope->isAdmin(), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('count(*) as c, avg(rating::numeric) as a')
            ->first();

        $aptFb = DB::table('appointment_feedback')
            ->whereBetween('submitted_at', [$from->utc(), $to->utc()])
            ->when(! empty($aptIds), fn ($q) => $q->whereIn('appointment_id', $aptIds))
            ->when(empty($aptIds) && ! $scope->isAdmin(), fn ($q) => $q->whereRaw('1=0'))
            ->selectRaw('count(*) as c, avg(rating::numeric) as a')
            ->first();

        $onbCount = (int) ($onboardingFb->c ?? 0);
        $aptCount = (int) ($aptFb->c ?? 0);
        $totalCount = $onbCount + $aptCount;

        $weightedSum = ($onbCount * (float) ($onboardingFb->a ?? 0)) + ($aptCount * (float) ($aptFb->a ?? 0));
        $avg = $totalCount > 0 ? round($weightedSum / $totalCount, 2) : null;

        // Response rate = submitted onboarding feedback / completed onboardings in period
        $completedOnboardings = (int) DB::table('onboarding_requests')
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from->utc(), $to->utc()])
            ->when(! empty($onbIds), fn ($q) => $q->whereIn('id', $onbIds))
            ->when(empty($onbIds) && ! $scope->isAdmin(), fn ($q) => $q->whereRaw('1=0'))
            ->count();

        return [
            'avg_rating'     => $avg,
            'feedback_count' => $totalCount,
            'response_rate'  => $completedOnboardings > 0 ? round($onbCount / $completedOnboardings, 4) : 0.0,
        ];
    }

    private function buildKpis(AnalyticsScope $scope, array $cur, ?array $prev): array
    {
        $get = fn ($section, $key, $default = 0) => $cur[$section][$key] ?? $default;
        $getP = fn ($section, $key) => $prev ? ($prev[$section][$key] ?? null) : null;

        $kpis = [
            'appointments_completed'    => KpiBuilder::build($get('apt', 'done'),       $getP('apt', 'done'),       'up'),
            'appointments_total'        => KpiBuilder::build($get('apt', 'total'),      $getP('apt', 'total'),      'up'),
            'completion_rate'           => KpiBuilder::build($get('apt', 'completion_rate'),   $getP('apt', 'completion_rate'),   'up'),
            'cancellation_rate'         => KpiBuilder::build($get('apt', 'cancellation_rate'), $getP('apt', 'cancellation_rate'), 'down'),
            'onboardings_active'        => KpiBuilder::build($get('onb', 'active'),     $getP('onb', 'active'),     'up'),
            'onboardings_completed'     => KpiBuilder::build($get('onb', 'completed'),  $getP('onb', 'completed'),  'up'),
            'avg_onboarding_duration_h' => KpiBuilder::build($get('onb', 'avg_duration_h', null), $getP('onb', 'avg_duration_h'), 'down'),
            'avg_rating'                => KpiBuilder::build($get('fb',  'avg_rating', null),    $getP('fb',  'avg_rating'),     'up'),
        ];

        if ($scope->isTrainer()) {
            $kpis['on_time_start_rate'] = KpiBuilder::build($get('apt', 'on_time_rate'), $getP('apt', 'on_time_rate'), 'up');
        } else {
            $kpis['feedback_response_rate'] = KpiBuilder::build($get('fb', 'response_rate'), $getP('fb', 'response_rate'), 'up');
        }

        return $kpis;
    }
}

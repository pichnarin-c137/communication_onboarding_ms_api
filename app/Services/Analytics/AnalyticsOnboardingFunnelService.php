<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenRoleException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsOnboardingFunnelService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        if ($scope->isTrainer()) {
            throw new ForbiddenRoleException('Trainers cannot view the onboarding funnel.');
        }

        $key = AnalyticsCacheKey::build('onboarding_funnel', $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
            'compare' => $period->compareMode,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters) {
            $stages = $this->buildStages($scope, $period->from, $period->to, $filters);

            $result = ['stages' => $stages];

            if ($period->compareFrom && $period->compareTo) {
                $result['compare_stages'] = $this->buildStages($scope, $period->compareFrom, $period->compareTo, $filters);
            }

            return $result;
        });
    }

    private function buildStages(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $demoCreated = $this->aptCount($scope, $filters, [
            'appointment_type' => 'demo',
        ], $from, $to, 'created_at');

        $demoDone = $this->aptCount($scope, $filters, [
            'appointment_type' => 'demo',
            'status' => 'done',
        ], $from, $to, 'created_at');

        $trainingCreated = $this->aptCount($scope, $filters, [
            'appointment_type' => 'training',
        ], $from, $to, 'created_at');

        $trainingDone = $this->aptCount($scope, $filters, [
            'appointment_type' => 'training',
            'status' => 'done',
        ], $from, $to, 'created_at');

        $onboardingStarted = $this->onbCount($scope, $from, $to, 'created_at');
        $onboardingCompleted = $this->onbCount($scope, $from, $to, 'created_at', 'completed');

        $feedbackSubmitted = $this->feedbackSubmittedCount($scope, $from, $to);

        $top = max($demoCreated, 1);
        $build = function (string $key, string $label, int $count, ?int $prev) use ($top) {
            return [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'drop_pct_from_prev' => $prev === null ? null : (($prev > 0) ? round((($prev - $count) / $prev) * 100, 1) : null),
                'conversion_pct_from_top' => round(($count / $top) * 100, 1),
            ];
        };

        return [
            $build('demo_created', 'Demo created', $demoCreated, null),
            $build('demo_done', 'Demo done', $demoDone, $demoCreated),
            $build('training_created', 'Training created', $trainingCreated, $demoDone),
            $build('training_done', 'Training done', $trainingDone, $trainingCreated),
            $build('onboarding_started', 'Onboarding started', $onboardingStarted, $trainingDone),
            $build('onboarding_completed', 'Onboarding completed', $onboardingCompleted, $onboardingStarted),
            $build('feedback_submitted', 'Feedback submitted', $feedbackSubmitted, $onboardingCompleted),
        ];
    }

    private function aptCount(AnalyticsScope $scope, array $filters, array $where, CarbonImmutable $from, CarbonImmutable $to, string $dateCol): int
    {
        $q = DB::table('appointments')
            ->whereNull('deleted_at')
            ->whereBetween($dateCol, [$from->utc(), $to->utc()])
            ->where($where);
        $scope->applyAppointmentScope($q);
        AnalyticsFilters::applyAppointment($q, $filters);
        return (int) $q->count();
    }

    private function onbCount(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, string $dateCol, ?string $status = null): int
    {
        $q = DB::table('onboarding_requests')
            ->whereNull('deleted_at')
            ->whereBetween($dateCol, [$from->utc(), $to->utc()]);
        if ($status !== null) {
            $q->where('status', $status);
        }
        $scope->applyOnboardingScope($q);
        return (int) $q->count();
    }

    private function feedbackSubmittedCount(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $onbQ = DB::table('onboarding_requests')->whereNull('deleted_at')->whereBetween('created_at', [$from->utc(), $to->utc()]);
        $scope->applyOnboardingScope($onbQ);
        $ids = $onbQ->pluck('id')->all();

        if (empty($ids) && ! $scope->isAdmin()) {
            return 0;
        }

        return (int) DB::table('onboarding_client_feedbacks')
            ->whereBetween('submitted_at', [$from->utc(), $to->utc()])
            ->when(! empty($ids), fn ($q) => $q->whereIn('onboarding_id', $ids))
            ->count();
    }
}

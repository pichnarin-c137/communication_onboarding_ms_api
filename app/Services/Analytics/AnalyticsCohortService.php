<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenRoleException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * GET /analytics/cohorts — onboarding completion cohorts (a retention grid).
 *
 * Onboardings are grouped by the period in which they were CREATED (the cohort).
 * For each cohort we track cumulative completion as a function of how many
 * periods elapsed between creation and completion (placed via completed_at).
 *
 * Each cohort only shows as many elapsed columns as have actually had time to
 * mature (min of max_elapsed and the periods elapsed since the cohort started),
 * so a brand-new cohort isn't shown as "0% complete" for periods that haven't
 * happened yet.
 */
class AnalyticsCohortService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $cohortBy, int $maxElapsed): array
    {
        if ($scope->isTrainer()) {
            throw new ForbiddenRoleException('Trainers cannot view onboarding cohorts.');
        }

        $cohortBy = in_array($cohortBy, ['month', 'week'], true) ? $cohortBy : 'month';
        $maxElapsed = max(1, min($maxElapsed, 24));

        $key = AnalyticsCacheKey::build('cohorts', $scope, $filters + [
            'from'        => $period->from->toDateString(),
            'to'          => $period->to->toDateString(),
            'cohort_by'   => $cohortBy,
            'max_elapsed' => $maxElapsed,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters, $cohortBy, $maxElapsed) {
            $rows = $this->fetchOnboardings($scope, $period->from, $period->to, $filters);
            $now = CarbonImmutable::now($period->timezone);

            return [
                'cohort_by' => $cohortBy,
                'metric'    => 'completion',
                'cohorts'   => $this->buildCohorts($rows, $cohortBy, $maxElapsed, $now, $period->timezone),
            ];
        });
    }

    private function fetchOnboardings(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): \Illuminate\Support\Collection
    {
        $q = DB::table('onboarding_requests')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from->utc(), $to->utc()]);
        $scope->applyOnboardingScope($q);
        AnalyticsFilters::applyOnboarding($q, $filters);

        return $q->get(['id', 'created_at', 'completed_at', 'status']);
    }

    private function buildCohorts(\Illuminate\Support\Collection $rows, string $cohortBy, int $maxElapsed, CarbonImmutable $now, string $tz): array
    {
        $cohorts = [];

        foreach ($rows as $r) {
            $created = CarbonImmutable::parse($r->created_at, 'UTC')->setTimezone($tz);
            $start = $this->periodStart($created, $cohortBy);
            $cohortKey = $cohortBy === 'month' ? $start->format('Y-m') : $start->toDateString();

            if (! isset($cohorts[$cohortKey])) {
                $cohorts[$cohortKey] = [
                    'start'        => $start,
                    'started'      => 0,
                    'completions'  => [], // elapsed => count
                    'durations'    => [], // days-to-complete for median
                ];
            }

            $cohorts[$cohortKey]['started']++;

            if ($r->status === 'completed' && $r->completed_at !== null) {
                $completed = CarbonImmutable::parse($r->completed_at, 'UTC')->setTimezone($tz);
                $elapsed = $this->elapsedPeriods($start, $this->periodStart($completed, $cohortBy), $cohortBy);
                $cohorts[$cohortKey]['completions'][$elapsed] = ($cohorts[$cohortKey]['completions'][$elapsed] ?? 0) + 1;
                $cohorts[$cohortKey]['durations'][] = $created->diffInDays($completed);
            }
        }

        ksort($cohorts); // oldest → newest (Y-m and Y-m-d both sort lexically)

        $out = [];
        foreach ($cohorts as $cohortKey => $c) {
            if ($c['started'] === 0) {
                continue;
            }

            $maturity = $this->elapsedPeriods($c['start'], $this->periodStart($now, $cohortBy), $cohortBy);
            $columns = min($maxElapsed, max(0, $maturity));

            $periods = [];
            $cum = 0;
            for ($e = 0; $e <= $columns; $e++) {
                $cum += $c['completions'][$e] ?? 0;
                $periods[] = [
                    'elapsed'       => $e,
                    'completed_cum' => $cum,
                    'completed_pct' => round($cum / $c['started'], 3),
                ];
            }

            $out[] = [
                'cohort'                  => $cohortKey,
                'label'                   => $this->label($c['start'], $cohortBy),
                'started'                 => $c['started'],
                'periods'                 => $periods,
                'median_days_to_complete' => $this->median($c['durations']),
            ];
        }

        return $out;
    }

    private function periodStart(CarbonImmutable $d, string $cohortBy): CarbonImmutable
    {
        return $cohortBy === 'month'
            ? $d->startOfMonth()
            : $d->startOfWeek(CarbonInterface::MONDAY);
    }

    private function elapsedPeriods(CarbonImmutable $start, CarbonImmutable $end, string $cohortBy): int
    {
        if ($end->lte($start)) {
            return 0;
        }

        return $cohortBy === 'month'
            ? (($end->year - $start->year) * 12) + ($end->month - $start->month)
            : (int) floor($start->diffInDays($end) / 7);
    }

    private function label(CarbonImmutable $start, string $cohortBy): string
    {
        return $cohortBy === 'month'
            ? $start->format('F Y')
            : 'Week of '.$start->format('M j, Y');
    }

    private function median(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }

        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        $median = ($n % 2 === 0)
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];

        return round((float) $median, 1);
    }
}

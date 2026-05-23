<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsTrendsService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $metric): array
    {
        $key = AnalyticsCacheKey::build("trends:{$metric}", $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
            'compare' => $period->compareMode,
            'group_by' => $period->groupBy,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters, $metric) {
            $series = $this->seriesFor($scope, $period, $filters, $metric, $period->from, $period->to);
            $compare = null;

            if ($period->compareFrom && $period->compareTo) {
                $compare = $this->seriesFor(
                    $scope,
                    $period,
                    $filters,
                    $metric,
                    $period->compareFrom,
                    $period->compareTo,
                );
            }

            $result = [
                'metric'   => $metric,
                'group_by' => $period->groupBy,
                'series'   => $series,
            ];
            if ($compare !== null) {
                $result['compare_series'] = $compare;
            }

            return $result;
        });
    }

    private function seriesFor(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $metric, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($metric) {
            'appointments_created'   => $this->appointmentBuckets($scope, $period, $filters, $from, $to, 'count(*)', '1', 'created_at'),
            'appointments_completed' => $this->appointmentBuckets($scope, $period, $filters, $from, $to, "count(*) filter (where status = 'done')", '1', 'scheduled_date'),
            'completion_rate'        => $this->appointmentBuckets($scope, $period, $filters, $from, $to, "count(*) filter (where status = 'done')::numeric / nullif(count(*),0)", "count(*)", 'scheduled_date'),
            'cancellation_rate'      => $this->appointmentBuckets($scope, $period, $filters, $from, $to, "count(*) filter (where status = 'cancelled')::numeric / nullif(count(*),0)", "count(*)", 'scheduled_date'),
            'onboardings_created'    => $this->onboardingBuckets($scope, $period, $filters, $from, $to, 'count(*)', 'created_at'),
            'onboardings_completed'  => $this->onboardingBuckets($scope, $period, $filters, $from, $to, "count(*) filter (where status = 'completed')", 'completed_at'),
            'avg_rating'             => $this->feedbackBuckets($scope, $period, $filters, $from, $to, 'avg', 'submitted_at'),
            'feedback_count'         => $this->feedbackBuckets($scope, $period, $filters, $from, $to, 'count', 'submitted_at'),
            default                  => [],
        };
    }

    private function appointmentBuckets(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, CarbonImmutable $from, CarbonImmutable $to, string $valueExpr, string $sampleExpr, string $dateColumn): array
    {
        $q = DB::table('appointments')->whereNull('appointments.deleted_at');
        $scope->applyAppointmentScope($q);
        AnalyticsFilters::applyAppointment($q, $filters);

        if ($dateColumn === 'scheduled_date') {
            $q->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()]);
            $bucket = $period->bucketExpression('scheduled_date');
        } else {
            $q->whereBetween($dateColumn, [$from->utc(), $to->utc()]);
            $bucket = $period->bucketExpressionTz($dateColumn);
        }

        $rows = $q->selectRaw("{$bucket} as bucket, ({$valueExpr}) as value, ({$sampleExpr}) as sample_size")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get();

        return $this->shapeSeries($rows);
    }

    private function onboardingBuckets(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, CarbonImmutable $from, CarbonImmutable $to, string $valueExpr, string $dateColumn): array
    {
        $q = DB::table('onboarding_requests')->whereNull('onboarding_requests.deleted_at');
        $scope->applyOnboardingScope($q);
        AnalyticsFilters::applyOnboarding($q, $filters);

        $q->whereBetween($dateColumn, [$from->utc(), $to->utc()]);
        $bucket = $period->bucketExpressionTz($dateColumn);

        $rows = $q->selectRaw("{$bucket} as bucket, ({$valueExpr}) as value, count(*) as sample_size")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get();

        return $this->shapeSeries($rows);
    }

    private function feedbackBuckets(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, CarbonImmutable $from, CarbonImmutable $to, string $mode, string $dateColumn): array
    {
        // Build scoped id sets and union both feedback tables.
        $onbQ = DB::table('onboarding_requests')->whereNull('deleted_at');
        $scope->applyOnboardingScope($onbQ);
        $onbIds = $onbQ->pluck('id')->all();

        $aptQ = DB::table('appointments')->whereNull('deleted_at');
        $scope->applyAppointmentScope($aptQ);
        AnalyticsFilters::applyAppointment($aptQ, $filters);
        $aptIds = $aptQ->pluck('id')->all();

        $bucketExpr = $period->bucketExpressionTz('submitted_at');

        $unionSql = '
            select submitted_at, rating from onboarding_client_feedbacks
            where submitted_at between ? and ?
              '.(empty($onbIds) ? "and false" : "and onboarding_id in (".$this->placeholders($onbIds).")").'
            union all
            select submitted_at, rating from appointment_feedback
            where submitted_at between ? and ?
              '.(empty($aptIds) ? "and false" : "and appointment_id in (".$this->placeholders($aptIds).")");

        $valueExpr = $mode === 'avg' ? 'avg(rating::numeric)' : 'count(*)';

        $sql = "
            with merged as ({$unionSql})
            select {$bucketExpr} as bucket, ({$valueExpr}) as value, count(*) as sample_size
            from merged
            group by {$bucketExpr}
            order by {$bucketExpr}
        ";

        $bindings = array_merge(
            [$from->utc(), $to->utc()],
            $onbIds,
            [$from->utc(), $to->utc()],
            $aptIds,
        );

        $rows = collect(DB::select($sql, $bindings));

        return $this->shapeSeries($rows);
    }

    private function placeholders(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    private function shapeSeries($rows): array
    {
        return collect($rows)->map(function ($r) {
            $r = (array) $r;
            return [
                'bucket'      => (string) $r['bucket'],
                'value'       => $r['value'] === null ? null : (is_numeric($r['value']) ? round((float) $r['value'], 4) : $r['value']),
                'sample_size' => (int) ($r['sample_size'] ?? 0),
            ];
        })->values()->all();
    }
}

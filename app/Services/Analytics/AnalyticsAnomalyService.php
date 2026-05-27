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
 * GET /analytics/anomalies — statistical outliers in key metrics.
 *
 * For each monitored metric a baseline is built from the buckets immediately
 * BEFORE the requested period; its mean + sample stddev define "normal".
 * Buckets inside the requested period whose z-score exceeds the configured
 * sigma threshold are flagged.
 *
 * A metric with fewer than `anomaly_min_baseline_buckets` of history is skipped
 * (we don't flag on thin evidence). A flat baseline (stddev ≈ 0) is also skipped
 * to avoid divide-by-zero / infinite z-scores.
 */
class AnalyticsAnomalyService
{
    /**
     * metric key => [label, good_direction, kind]
     * kind: 'apt' (appointment-bucketed) | 'rating' (feedback-bucketed)
     *
     * @var array<string,array{0:string,1:string,2:string,3:string}>
     */
    private const METRICS = [
        'completion_rate'    => ['Completion rate',   'up',   'apt', "count(*) filter (where status = 'done')::numeric / nullif(count(*),0)"],
        'cancellation_rate'  => ['Cancellation rate', 'down', 'apt', "count(*) filter (where status = 'cancelled')::numeric / nullif(count(*),0)"],
        'no_show_rate'       => ['No-show rate',       'down', 'apt', "count(*) filter (where status = 'done' and student_count = 0)::numeric / nullif(count(*),0)"],
        'avg_rating'         => ['Average rating',     'up',   'rating', ''],
        'appointments_total' => ['Appointments total', 'up',   'apt', 'count(*)'],
    ];

    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        if ($scope->isTrainer()) {
            throw new ForbiddenRoleException('Trainers cannot view anomaly analytics.');
        }

        $key = AnalyticsCacheKey::build('anomalies', $scope, $filters + [
            'from'     => $period->from->toDateString(),
            'to'       => $period->to->toDateString(),
            'group_by' => $period->groupBy,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters) {
            $sigma = (float) config('coms.analytics.anomaly_sigma_threshold', 2.0);
            $minBaseline = (int) config('coms.analytics.anomaly_min_baseline_buckets', 6);

            [$baselineFrom, $baselineTo] = $this->baselineWindow($period, $minBaseline);

            $anomalies = [];

            foreach (self::METRICS as $metric => [$label, $goodDir, $kind, $valueExpr]) {
                $baseline = array_values($this->series($scope, $period, $filters, $baselineFrom, $baselineTo, $kind, $valueExpr));

                if (count($baseline) < $minBaseline) {
                    continue; // not enough history — don't false-flag
                }

                $mean = $this->mean($baseline);
                $stddev = $this->stddev($baseline, $mean);

                if ($stddev < 1e-9) {
                    continue; // flat baseline — no meaningful z-score
                }

                $current = $this->series($scope, $period, $filters, $period->from, $period->to, $kind, $valueExpr);

                foreach ($current as $bucket => $value) {
                    $z = ($value - $mean) / $stddev;

                    if (abs($z) <= $sigma) {
                        continue;
                    }

                    $direction = $value >= $mean ? 'up' : 'down';

                    $anomalies[] = [
                        'metric'          => $metric,
                        'label'           => $label,
                        'bucket'          => $bucket,
                        'value'           => round($value, 4),
                        'baseline_mean'   => round($mean, 4),
                        'baseline_stddev' => round($stddev, 4),
                        'z_score'         => round($z, 2),
                        'direction'       => $direction,
                        'good_direction'  => $goodDir,
                        'is_concerning'   => $direction !== $goodDir,
                        'severity'        => $this->severity($z),
                    ];
                }
            }

            // Most severe / most recent first.
            usort($anomalies, fn ($a, $b) => abs($b['z_score']) <=> abs($a['z_score']) ?: strcmp($b['bucket'], $a['bucket']));

            return [
                'anomalies' => $anomalies,
                'baseline_window' => [
                    'from'    => $baselineFrom->toDateString(),
                    'to'      => $baselineTo->toDateString(),
                    'buckets' => $this->countBuckets($baselineFrom, $baselineTo, $period->groupBy),
                ],
                'metrics_monitored' => array_keys(self::METRICS),
            ];
        });
    }

    /**
     * Bucketed metric values for a window. Returns bucket(date string) => float.
     * Only buckets that actually contain rows are returned (no fabricated zeros).
     *
     * @return array<string,float>
     */
    private function series(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, CarbonImmutable $from, CarbonImmutable $to, string $kind, string $valueExpr): array
    {
        return $kind === 'rating'
            ? $this->ratingSeries($scope, $period, $filters, $from, $to)
            : $this->appointmentSeries($scope, $period, $filters, $from, $to, $valueExpr);
    }

    private function appointmentSeries(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, CarbonImmutable $from, CarbonImmutable $to, string $valueExpr): array
    {
        $q = DB::table('appointments')
            ->whereNull('appointments.deleted_at')
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()]);
        $scope->applyAppointmentScope($q);
        AnalyticsFilters::applyAppointment($q, $filters);

        $bucket = $period->bucketExpression('scheduled_date');

        $rows = $q->selectRaw("{$bucket} as bucket, ({$valueExpr}) as value")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if ($r->value === null) {
                continue;
            }
            $out[(string) $r->bucket] = (float) $r->value;
        }

        return $out;
    }

    private function ratingSeries(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $onbQ = DB::table('onboarding_requests')->whereNull('deleted_at');
        $scope->applyOnboardingScope($onbQ);
        $onbIds = $onbQ->pluck('id')->all();

        $aptQ = DB::table('appointments')->whereNull('deleted_at');
        $scope->applyAppointmentScope($aptQ);
        AnalyticsFilters::applyAppointment($aptQ, $filters);
        $aptIds = $aptQ->pluck('id')->all();

        if (empty($onbIds) && empty($aptIds) && ! $scope->isAdmin()) {
            return [];
        }

        $bucketExpr = $period->bucketExpressionTz('submitted_at');

        $unionSql = '
            select submitted_at, rating from onboarding_client_feedbacks
            where submitted_at between ? and ?
              '.(empty($onbIds) ? 'and false' : 'and onboarding_id in ('.$this->placeholders($onbIds).')').'
            union all
            select submitted_at, rating from appointment_feedback
            where submitted_at between ? and ?
              '.(empty($aptIds) ? 'and false' : 'and appointment_id in ('.$this->placeholders($aptIds).')');

        $sql = "with merged as ({$unionSql})
                select {$bucketExpr} as bucket, avg(rating::numeric) as value
                from merged group by {$bucketExpr} order by {$bucketExpr}";

        $bindings = array_merge([$from->utc(), $to->utc()], $onbIds, [$from->utc(), $to->utc()], $aptIds);

        $out = [];
        foreach (DB::select($sql, $bindings) as $r) {
            if ($r->value === null) {
                continue;
            }
            $out[(string) $r->bucket] = (float) $r->value;
        }

        return $out;
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function baselineWindow(AnalyticsPeriod $period, int $minBaseline): array
    {
        // Generous lookback so even sparse data clears the min-baseline bar.
        $lookback = max($minBaseline * 2, 12);

        $to = $period->from->subDay()->endOfDay();

        $from = match ($period->groupBy) {
            'day'   => $period->from->subDays($lookback)->startOfDay(),
            'month' => $period->from->subMonths($lookback)->startOfMonth(),
            default => $period->from->subWeeks($lookback)->startOfWeek(CarbonInterface::MONDAY),
        };

        return [$from, $to];
    }

    private function countBuckets(CarbonImmutable $from, CarbonImmutable $to, string $groupBy): int
    {
        $step = match ($groupBy) {
            'day'   => '1 day',
            'month' => '1 month',
            default => '1 week',
        };

        $start = match ($groupBy) {
            'day'   => $from->startOfDay(),
            'month' => $from->startOfMonth(),
            default => $from->startOfWeek(CarbonInterface::MONDAY),
        };

        $count = 0;
        for ($d = $start; $d->lte($to); $d = $d->add($step)) {
            $count++;
        }

        return $count;
    }

    private function mean(array $values): float
    {
        return array_sum($values) / count($values);
    }

    private function stddev(array $values, float $mean): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }

        return sqrt($sumSq / ($n - 1)); // sample stddev
    }

    private function severity(float $z): string
    {
        $abs = abs($z);

        return match (true) {
            $abs >= 4.0 => 'high',
            $abs >= 3.0 => 'medium',
            default     => 'low',
        };
    }

    private function placeholders(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}

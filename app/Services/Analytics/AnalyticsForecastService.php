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
 * GET /analytics/forecast — project a metric forward N periods.
 *
 * The history series uses the same buckets as /analytics/trends. Three
 * deterministic, dependency-light methods are offered:
 *
 *   - holt        Holt's linear (double exponential smoothing), α=0.5 β=0.3.
 *                 Adapts to recent level + trend; good default.
 *   - linear      Ordinary least-squares line; trend_slope is the regression slope.
 *   - moving_avg  Flat projection at the mean of the last min(n,4) points.
 *
 * Confidence band = ±z · σ · √h where σ is the in-sample residual stddev,
 * z = 1.2816 (80%), and h is the 1-based step ahead (bands widen with horizon).
 * With fewer than 4 history points we return the history and an empty forecast
 * plus model.note = "insufficient_history".
 */
class AnalyticsForecastService
{
    private const Z_80 = 1.2816;

    private const CONFIDENCE_LEVEL = 0.80;

    /** metric => kind ('count' | 'rate' | 'rating'). */
    private const METRICS = [
        'onboardings_completed'  => 'count',
        'onboardings_created'    => 'count',
        'appointments_created'   => 'count',
        'appointments_completed' => 'count',
        'completion_rate'        => 'rate',
        'avg_rating'             => 'rating',
    ];

    public function __construct(private AnalyticsCache $cache) {}

    public static function allowedMetrics(): array
    {
        return array_keys(self::METRICS);
    }

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $metric, int $horizon, ?string $method = null): array
    {
        if ($scope->isTrainer()) {
            throw new ForbiddenRoleException('Trainers cannot view forecasts.');
        }

        $metric = isset(self::METRICS[$metric]) ? $metric : 'onboardings_completed';
        $method = in_array($method, ['holt', 'linear', 'moving_avg'], true)
            ? $method
            : (string) config('coms.analytics.forecast_method', 'holt');
        $horizon = max(1, min($horizon, 24));

        $key = AnalyticsCacheKey::build("forecast:{$metric}", $scope, $filters + [
            'from'     => $period->from->toDateString(),
            'to'       => $period->to->toDateString(),
            'group_by' => $period->groupBy,
            'method'   => $method,
            'horizon'  => $horizon,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters, $metric, $horizon, $method) {
            $kind = self::METRICS[$metric];
            $history = $this->buildHistory($scope, $period, $filters, $metric, $kind);

            $historyOut = array_map(
                fn ($b, $v) => ['bucket' => $b, 'value' => $this->roundValue($v, $kind)],
                array_keys($history),
                array_values($history),
            );

            if (count($history) < 4) {
                return [
                    'metric'   => $metric,
                    'group_by' => $period->groupBy,
                    'method'   => $method,
                    'history'  => $historyOut,
                    'forecast' => [],
                    'horizon'  => $horizon,
                    'model'    => [
                        'trend_slope'      => null,
                        'confidence_level' => self::CONFIDENCE_LEVEL,
                        'mape'             => null,
                        'note'             => 'insufficient_history',
                    ],
                ];
            }

            $values = array_values($history);
            $fit = match ($method) {
                'linear'     => $this->fitLinear($values),
                'moving_avg' => $this->fitMovingAvg($values),
                default      => $this->fitHolt($values),
            };

            $forecast = $this->projectForecast($history, $fit, $horizon, $period, $kind);

            return [
                'metric'   => $metric,
                'group_by' => $period->groupBy,
                'method'   => $method,
                'history'  => $historyOut,
                'forecast' => $forecast,
                'horizon'  => $horizon,
                'model'    => [
                    'trend_slope'      => round($fit['slope'], 4),
                    'confidence_level' => self::CONFIDENCE_LEVEL,
                    'mape'             => $fit['mape'] === null ? null : round($fit['mape'], 4),
                ],
            ];
        });
    }

    // ---- history -----------------------------------------------------------

    /** @return array<string,float>  ordered bucket(date) => value */
    private function buildHistory(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $metric, string $kind): array
    {
        $raw = $this->rawSeries($scope, $period, $filters, $metric);

        $out = [];
        $last = null;
        foreach ($period->bucketDates() as $date) {
            if (array_key_exists($date, $raw)) {
                $out[$date] = $raw[$date];
                $last = $raw[$date];
            } elseif ($kind === 'count') {
                $out[$date] = 0.0; // an empty bucket is a real zero
            } elseif ($last !== null) {
                $out[$date] = $last; // rate/rating: carry last known forward
            }
            // rate/rating with no prior value yet → skip leading gap
        }

        return $out;
    }

    /** @return array<string,float> */
    private function rawSeries(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $metric): array
    {
        return match ($metric) {
            'onboardings_created'    => $this->onbSeries($scope, $period, 'count(*)', 'created_at'),
            'onboardings_completed'  => $this->onbSeries($scope, $period, "count(*) filter (where status = 'completed')", 'completed_at'),
            'appointments_created'   => $this->aptSeries($scope, $period, $filters, 'count(*)', 'created_at'),
            'appointments_completed' => $this->aptSeries($scope, $period, $filters, "count(*) filter (where status = 'done')", 'scheduled_date'),
            'completion_rate'        => $this->aptSeries($scope, $period, $filters, "count(*) filter (where status = 'done')::numeric / nullif(count(*),0)", 'scheduled_date'),
            'avg_rating'             => $this->ratingSeries($scope, $period, $filters),
            default                  => [],
        };
    }

    private function aptSeries(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $valueExpr, string $dateCol): array
    {
        $q = DB::table('appointments')->whereNull('appointments.deleted_at');
        $scope->applyAppointmentScope($q);
        AnalyticsFilters::applyAppointment($q, $filters);

        if ($dateCol === 'scheduled_date') {
            $q->whereBetween('scheduled_date', [$period->from->toDateString(), $period->to->toDateString()]);
            $bucket = $period->bucketExpression('scheduled_date');
        } else {
            $q->whereBetween($dateCol, [$period->from->utc(), $period->to->utc()]);
            $bucket = $period->bucketExpressionTz($dateCol);
        }

        return $this->collectBuckets(
            $q->selectRaw("{$bucket} as bucket, ({$valueExpr}) as value")->groupByRaw($bucket)->orderByRaw($bucket)->get()
        );
    }

    private function onbSeries(AnalyticsScope $scope, AnalyticsPeriod $period, string $valueExpr, string $dateCol): array
    {
        $q = DB::table('onboarding_requests')->whereNull('onboarding_requests.deleted_at')
            ->whereBetween($dateCol, [$period->from->utc(), $period->to->utc()]);
        $scope->applyOnboardingScope($q);

        $bucket = $period->bucketExpressionTz($dateCol);

        return $this->collectBuckets(
            $q->selectRaw("{$bucket} as bucket, ({$valueExpr}) as value")->groupByRaw($bucket)->orderByRaw($bucket)->get()
        );
    }

    private function ratingSeries(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
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
        $ph = fn ($ids) => implode(',', array_fill(0, count($ids), '?'));

        $unionSql = '
            select submitted_at, rating from onboarding_client_feedbacks
            where submitted_at between ? and ? '.(empty($onbIds) ? 'and false' : 'and onboarding_id in ('.$ph($onbIds).')').'
            union all
            select submitted_at, rating from appointment_feedback
            where submitted_at between ? and ? '.(empty($aptIds) ? 'and false' : 'and appointment_id in ('.$ph($aptIds).')');

        $sql = "with merged as ({$unionSql})
                select {$bucketExpr} as bucket, avg(rating::numeric) as value
                from merged group by {$bucketExpr} order by {$bucketExpr}";

        $bindings = array_merge([$period->from->utc(), $period->to->utc()], $onbIds, [$period->from->utc(), $period->to->utc()], $aptIds);

        return $this->collectBuckets(collect(DB::select($sql, $bindings)));
    }

    private function collectBuckets($rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ($r->value === null) {
                continue;
            }
            $out[(string) $r->bucket] = (float) $r->value;
        }

        return $out;
    }

    // ---- models ------------------------------------------------------------

    /** @return array{slope:float,forecast:callable,mape:?float} */
    private function fitLinear(array $y): array
    {
        $n = count($y);
        $sumX = $sumY = $sumXY = $sumX2 = 0.0;
        foreach ($y as $i => $v) {
            $sumX += $i;
            $sumY += $v;
            $sumXY += $i * $v;
            $sumX2 += $i * $i;
        }

        $denom = ($n * $sumX2) - ($sumX ** 2);
        $slope = $denom != 0.0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denom : 0.0;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        $fitted = fn (int $i) => $intercept + ($slope * $i);
        $residuals = [];
        foreach ($y as $i => $v) {
            $residuals[] = $v - $fitted($i);
        }

        return [
            'slope'    => $slope,
            'sigma'    => $this->residualSigma($residuals),
            'mape'     => $this->mape($y, array_map($fitted, array_keys($y))),
            'forecast' => fn (int $h) => $fitted($n - 1 + $h), // h = 1..horizon
        ];
    }

    /** @return array{slope:float,forecast:callable,mape:?float,sigma:float} */
    private function fitMovingAvg(array $y): array
    {
        $n = count($y);
        $k = min($n, 4);
        $window = array_slice($y, -$k);
        $avg = array_sum($window) / $k;

        // In-sample one-step fits for residual sigma + mape.
        $fittedVals = [];
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - $k);
            $slice = array_slice($y, $start, $i - $start);
            $fittedVals[$i] = empty($slice) ? $y[$i] : array_sum($slice) / count($slice);
        }
        $residuals = [];
        foreach ($y as $i => $v) {
            $residuals[] = $v - $fittedVals[$i];
        }

        return [
            'slope'    => 0.0,
            'sigma'    => $this->residualSigma($residuals),
            'mape'     => $this->mape($y, $fittedVals),
            'forecast' => fn (int $h) => $avg,
        ];
    }

    /** @return array{slope:float,forecast:callable,mape:?float,sigma:float} */
    private function fitHolt(array $y): array
    {
        $alpha = 0.5;
        $beta = 0.3;
        $n = count($y);

        $level = $y[0];
        $trend = $y[1] - $y[0];

        $fittedVals = [$y[0]];
        for ($i = 1; $i < $n; $i++) {
            $fittedVals[$i] = $level + $trend; // one-step-ahead fit
            $prevLevel = $level;
            $level = ($alpha * $y[$i]) + ((1 - $alpha) * ($level + $trend));
            $trend = ($beta * ($level - $prevLevel)) + ((1 - $beta) * $trend);
        }

        $residuals = [];
        foreach ($y as $i => $v) {
            $residuals[] = $v - $fittedVals[$i];
        }

        $finalLevel = $level;
        $finalTrend = $trend;

        return [
            'slope'    => $finalTrend,
            'sigma'    => $this->residualSigma($residuals),
            'mape'     => $this->mape($y, $fittedVals),
            'forecast' => fn (int $h) => $finalLevel + ($h * $finalTrend),
        ];
    }

    private function projectForecast(array $history, array $fit, int $horizon, AnalyticsPeriod $period, string $kind): array
    {
        $lastBucket = CarbonImmutable::parse(array_key_last($history), $period->timezone);
        $sigma = $fit['sigma'];

        $out = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $bucketDate = $this->stepBucket($lastBucket, $period->groupBy, $h);
            $value = ($fit['forecast'])($h);
            $band = self::Z_80 * $sigma * sqrt($h);

            $out[] = [
                'bucket' => $bucketDate,
                'value'  => $this->roundValue($value, $kind),
                'lower'  => $this->roundValue($value - $band, $kind),
                'upper'  => $this->roundValue($value + $band, $kind),
            ];
        }

        return $out;
    }

    private function stepBucket(CarbonImmutable $last, string $groupBy, int $h): string
    {
        return match ($groupBy) {
            'day'   => $last->addDays($h)->toDateString(),
            'month' => $last->addMonths($h)->startOfMonth()->toDateString(),
            default => $last->addWeeks($h)->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
        };
    }

    private function residualSigma(array $residuals): float
    {
        $n = count($residuals);
        if ($n < 2) {
            return 0.0;
        }

        $sumSq = 0.0;
        foreach ($residuals as $r) {
            $sumSq += $r ** 2;
        }

        return sqrt($sumSq / max(1, $n - 1));
    }

    private function mape(array $actual, array $fitted): ?float
    {
        $sum = 0.0;
        $count = 0;
        foreach ($actual as $i => $v) {
            if ($v == 0.0) {
                continue; // undefined percentage error
            }
            $sum += abs(($v - $fitted[$i]) / $v);
            $count++;
        }

        return $count > 0 ? $sum / $count : null;
    }

    private function roundValue(float $value, string $kind): float|int
    {
        return match ($kind) {
            'count'  => max(0, (int) round($value)),
            'rate'   => round(max(0.0, min(1.0, $value)), 4),
            'rating' => round(max(0.0, min(5.0, $value)), 2),
            default  => round($value, 4),
        };
    }
}

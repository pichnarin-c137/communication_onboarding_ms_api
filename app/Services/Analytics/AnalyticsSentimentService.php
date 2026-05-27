<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenRoleException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use App\Services\Analytics\Support\SentimentClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GET /analytics/sentiment — aggregate stored comment sentiment + recurring themes.
 *
 * Per-comment sentiment is persisted (see SentimentClassifier / the migration /
 * the backfill command); this service only AGGREGATES stored values, so it is
 * cheap and is cached for the long sentiment TTL rather than the 5-min TTL.
 */
class AnalyticsSentimentService
{
    private const THEME_LABELS = [
        'punctuality'    => 'Punctuality',
        'patience'       => 'Patience',
        'clarity'        => 'Clarity',
        'knowledge'      => 'Knowledge',
        'responsiveness' => 'Responsiveness',
        'friendliness'   => 'Friendliness',
        'pace'           => 'Pace',
        'materials'      => 'Materials',
    ];

    public function __construct(
        private AnalyticsCache $cache,
        private SentimentClassifier $classifier,
    ) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        if ($scope->isTrainer()) {
            throw new ForbiddenRoleException('Trainers cannot view comment sentiment analytics.');
        }

        $key = AnalyticsCacheKey::build('sentiment', $scope, $filters + [
            'from'     => $period->from->toDateString(),
            'to'       => $period->to->toDateString(),
            'compare'  => $period->compareMode,
            'group_by' => $period->groupBy,
        ]);

        $ttl = (int) config('coms.analytics.sentiment_cache_ttl', 86400);

        return $this->cache->remember($key, $scope, $ttl, function () use ($scope, $period, $filters) {
            $rows = $this->fetchRows($scope, $period->from, $period->to, $filters);
            $prev = ($period->compareFrom && $period->compareTo)
                ? $this->fetchRows($scope, $period->compareFrom, $period->compareTo, $filters)
                : collect();

            return [
                'summary'        => $this->summary($rows, $prev),
                'trend'          => $this->trend($rows, $period),
                'themes'         => $this->themes($rows, $prev, $period),
                'representative' => $this->representative($rows),
            ];
        });
    }

    /**
     * @return Collection<int,object>  rows: {source, score, label, rating, comment, client_name, submitted_at}
     */
    private function fetchRows(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        [$onbIds, $aptIds] = $this->scopedIds($scope, $filters);

        $rows = collect();

        if (! empty($onbIds) || $scope->isAdmin()) {
            $rows = $rows->merge(
                DB::table('onboarding_client_feedbacks as f')
                    ->join('onboarding_requests as o', 'o.id', '=', 'f.onboarding_id')
                    ->leftJoin('clients as c', 'c.id', '=', 'o.client_id')
                    ->whereNull('o.deleted_at')
                    ->whereBetween('f.submitted_at', [$from->utc(), $to->utc()])
                    ->whereNotNull('f.sentiment_score')
                    ->whereNotNull('f.comment')
                    ->where('f.comment', '<>', '')
                    ->when(! empty($onbIds), fn ($q) => $q->whereIn('f.onboarding_id', $onbIds))
                    ->get([
                        'f.rating', 'f.comment', 'f.sentiment_score', 'f.sentiment_label',
                        'f.submitted_at', 'c.company_name as client_name',
                        DB::raw("'onboarding' as source"),
                    ])
            );
        }

        if (! empty($aptIds) || $scope->isAdmin()) {
            $rows = $rows->merge(
                DB::table('appointment_feedback as f')
                    ->join('appointments as a', 'a.id', '=', 'f.appointment_id')
                    ->leftJoin('clients as c', 'c.id', '=', 'a.client_id')
                    ->whereNull('a.deleted_at')
                    ->whereBetween('f.submitted_at', [$from->utc(), $to->utc()])
                    ->whereNotNull('f.sentiment_score')
                    ->whereNotNull('f.comment')
                    ->where('f.comment', '<>', '')
                    ->when(! empty($aptIds), fn ($q) => $q->whereIn('f.appointment_id', $aptIds))
                    ->get([
                        'f.rating', 'f.comment', 'f.sentiment_score', 'f.sentiment_label',
                        'f.submitted_at', 'c.company_name as client_name',
                        DB::raw("'appointment' as source"),
                    ])
            );
        }

        return $rows->map(function ($r) {
            $r->sentiment_score = (float) $r->sentiment_score;
            $r->rating = $r->rating !== null ? (int) $r->rating : null;

            return $r;
        })->values();
    }

    private function summary(Collection $rows, Collection $prev): array
    {
        $count = $rows->count();

        if ($count === 0) {
            return [
                'analyzed_count' => 0,
                'positive_pct'   => 0.0,
                'neutral_pct'    => 0.0,
                'negative_pct'   => 0.0,
                'sentiment_score'          => 0.0,
                'previous_sentiment_score' => $prev->isNotEmpty() ? round((float) $prev->avg('sentiment_score'), 2) : null,
                'delta_pct'      => null,
            ];
        }

        $positive = $rows->where('sentiment_label', 'positive')->count();
        $neutral  = $rows->where('sentiment_label', 'neutral')->count();
        $negative = $rows->where('sentiment_label', 'negative')->count();

        $mean = round((float) $rows->avg('sentiment_score'), 2);
        $prevMean = $prev->isNotEmpty() ? round((float) $prev->avg('sentiment_score'), 2) : null;

        $delta = ($prevMean !== null && $prevMean != 0.0)
            ? round((($mean - $prevMean) / abs($prevMean)) * 100, 1)
            : null;

        return [
            'analyzed_count' => $count,
            'positive_pct'   => round($positive / $count, 2),
            'neutral_pct'    => round($neutral / $count, 2),
            'negative_pct'   => round($negative / $count, 2),
            'sentiment_score'          => $mean,
            'previous_sentiment_score' => $prevMean,
            'delta_pct'      => $delta,
        ];
    }

    private function trend(Collection $rows, AnalyticsPeriod $period): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $buckets = $rows
            ->groupBy(fn ($r) => $this->bucketKey($r->submitted_at, $period))
            ->map(fn (Collection $g, string $bucket) => [
                'bucket'          => $bucket,
                'sentiment_score' => round((float) $g->avg('sentiment_score'), 2),
                'count'           => $g->count(),
            ])
            ->values()
            ->sortBy('bucket')
            ->values();

        return $buckets->all();
    }

    private function themes(Collection $rows, Collection $prev, AnalyticsPeriod $period): array
    {
        $current = $this->themeStats($rows);
        $previous = $this->themeStats($prev);
        $hasCompare = $period->compareFrom && $period->compareTo;

        $themes = [];
        foreach ($current as $theme => $stat) {
            $prevMentions = $previous[$theme]['mentions'] ?? 0;

            $themes[] = [
                'theme'        => $theme,
                'label'        => self::THEME_LABELS[$theme] ?? ucfirst($theme),
                'mentions'     => $stat['mentions'],
                'sentiment'    => round($stat['sum'] / $stat['mentions'], 2),
                'trend'        => $hasCompare ? $this->themeTrend($stat['mentions'], $prevMentions) : 'flat',
                'sample_quote' => $stat['sample'],
            ];
        }

        usort($themes, fn ($a, $b) => $b['mentions'] <=> $a['mentions']);

        return array_slice($themes, 0, 8);
    }

    /**
     * @return array<string,array{mentions:int,sum:float,sample:?string}>
     */
    private function themeStats(Collection $rows): array
    {
        $stats = [];

        foreach ($rows as $r) {
            foreach ($this->classifier->detectThemes((string) $r->comment) as $theme) {
                if (! isset($stats[$theme])) {
                    $stats[$theme] = ['mentions' => 0, 'sum' => 0.0, 'sample' => null, 'sample_abs' => -1.0];
                }
                $stats[$theme]['mentions']++;
                $stats[$theme]['sum'] += $r->sentiment_score;

                // Keep the most opinionated comment as the representative quote.
                $abs = abs($r->sentiment_score);
                if ($abs > $stats[$theme]['sample_abs']) {
                    $stats[$theme]['sample_abs'] = $abs;
                    $stats[$theme]['sample'] = (string) $r->comment;
                }
            }
        }

        return $stats;
    }

    private function themeTrend(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? 'up' : 'flat';
        }

        $ratio = $current / $previous;

        return match (true) {
            $ratio > 1.15 => 'up',
            $ratio < 0.85 => 'down',
            default       => 'flat',
        };
    }

    private function representative(Collection $rows): array
    {
        $shape = fn ($r) => [
            'comment'      => (string) $r->comment,
            'rating'       => $r->rating,
            'client_name'  => $r->client_name,
            'submitted_at' => (string) $r->submitted_at,
        ];

        $positive = $rows->where('sentiment_label', 'positive')
            ->sortByDesc('sentiment_score')->take(3)->map($shape)->values()->all();

        $negative = $rows->where('sentiment_label', 'negative')
            ->sortBy('sentiment_score')->take(3)->map($shape)->values()->all();

        return ['positive' => $positive, 'negative' => $negative];
    }

    private function bucketKey(string $submittedAt, AnalyticsPeriod $period): string
    {
        $d = CarbonImmutable::parse($submittedAt, 'UTC')->setTimezone($period->timezone);

        return match ($period->groupBy) {
            'day'   => $d->startOfDay()->toDateString(),
            'month' => $d->startOfMonth()->toDateString(),
            default => $d->startOfWeek(\Carbon\CarbonInterface::MONDAY)->toDateString(),
        };
    }

    private function scopedIds(AnalyticsScope $scope, array $filters): array
    {
        $onbQ = DB::table('onboarding_requests')->whereNull('deleted_at');
        $scope->applyOnboardingScope($onbQ);
        $onbIds = $onbQ->pluck('id')->all();

        $aptQ = DB::table('appointments')->whereNull('deleted_at');
        $scope->applyAppointmentScope($aptQ);
        AnalyticsFilters::applyAppointment($aptQ, $filters);
        $aptIds = $aptQ->pluck('id')->all();

        return [$onbIds, $aptIds];
    }
}

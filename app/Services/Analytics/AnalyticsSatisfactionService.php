<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use App\Services\Analytics\Support\KpiBuilder;
use App\Services\Analytics\Support\TrainerAttribution;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsSatisfactionService
{
    public function __construct(
        private AnalyticsCache $cache,
        private TrainerAttribution $attribution,
    ) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        $key = AnalyticsCacheKey::build('satisfaction', $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
            'compare' => $period->compareMode,
            'group_by' => $period->groupBy,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters) {
            $cur = $this->merged($scope, $period->from, $period->to, $filters);
            $prev = ($period->compareFrom && $period->compareTo)
                ? $this->merged($scope, $period->compareFrom, $period->compareTo, $filters)
                : null;

            $summary = [
                'avg_rating'     => KpiBuilder::build($cur['avg'], $prev['avg'] ?? null, 'up'),
                'feedback_count' => KpiBuilder::build($cur['count'], $prev['count'] ?? null, 'up'),
                'response_rate'  => KpiBuilder::build($cur['response_rate'], $prev['response_rate'] ?? null, 'up'),
                'promoter_pct'   => $cur['count'] > 0 ? round($cur['promoter'] / $cur['count'], 4) : 0.0,
                'passive_pct'    => $cur['count'] > 0 ? round($cur['passive'] / $cur['count'], 4) : 0.0,
                'detractor_pct'  => $cur['count'] > 0 ? round($cur['detractor'] / $cur['count'], 4) : 0.0,
            ];

            $distribution = [];
            foreach ([1, 2, 3, 4, 5] as $r) {
                $distribution[] = ['rating' => $r, 'count' => (int) ($cur['dist'][$r] ?? 0)];
            }

            return [
                'summary' => $summary,
                'distribution' => $distribution,
                'trend' => $this->trend($scope, $period, $filters),
                'low_rating_alerts' => $this->lowRatingAlerts($scope, $filters),
            ];
        });
    }

    private function merged(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        [$onbIds, $aptIds] = $this->scopedIds($scope, $from, $to, $filters);

        $rows = collect();

        if (! empty($onbIds) || $scope->isAdmin()) {
            $rows = $rows->merge(DB::table('onboarding_client_feedbacks')
                ->whereBetween('submitted_at', [$from->utc(), $to->utc()])
                ->when(! empty($onbIds), fn ($q) => $q->whereIn('onboarding_id', $onbIds))
                ->get(['rating', 'submitted_at']));
        }

        if (! empty($aptIds) || $scope->isAdmin()) {
            $rows = $rows->merge(DB::table('appointment_feedback')
                ->whereBetween('submitted_at', [$from->utc(), $to->utc()])
                ->when(! empty($aptIds), fn ($q) => $q->whereIn('appointment_id', $aptIds))
                ->get(['rating', 'submitted_at']));
        }

        $count = $rows->count();
        $avg = $count > 0 ? round($rows->avg('rating'), 2) : null;

        $promoter = $rows->where('rating', 5)->count();
        $passive = $rows->where('rating', 4)->count();
        $detractor = $rows->filter(fn ($r) => $r->rating <= 3)->count();

        $dist = [];
        foreach ($rows as $r) {
            $dist[$r->rating] = ($dist[$r->rating] ?? 0) + 1;
        }

        // Response rate = onboarding feedback / completed onboardings
        $completedOnb = (int) DB::table('onboarding_requests')
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from->utc(), $to->utc()])
            ->when(! empty($onbIds), fn ($q) => $q->whereIn('id', $onbIds))
            ->when(empty($onbIds) && ! $scope->isAdmin(), fn ($q) => $q->whereRaw('1=0'))
            ->count();

        $onbCount = DB::table('onboarding_client_feedbacks')
            ->whereBetween('submitted_at', [$from->utc(), $to->utc()])
            ->when(! empty($onbIds), fn ($q) => $q->whereIn('onboarding_id', $onbIds))
            ->count();

        $responseRate = $completedOnb > 0 ? round($onbCount / $completedOnb, 4) : 0.0;

        return [
            'avg' => $avg,
            'count' => $count,
            'promoter' => $promoter,
            'passive' => $passive,
            'detractor' => $detractor,
            'dist' => $dist,
            'response_rate' => $responseRate,
        ];
    }

    private function trend(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        [$onbIds, $aptIds] = $this->scopedIds($scope, $period->from, $period->to, $filters);

        $unionParts = [];
        $bindings = [];

        if (! empty($onbIds) || $scope->isAdmin()) {
            $unionParts[] = 'select submitted_at, rating from onboarding_client_feedbacks where submitted_at between ? and ?'
                .($onbIds ? ' and onboarding_id in ('.$this->ph($onbIds).')' : '');
            $bindings = array_merge($bindings, [$period->from->utc(), $period->to->utc()], $onbIds);
        }

        if (! empty($aptIds) || $scope->isAdmin()) {
            $unionParts[] = 'select submitted_at, rating from appointment_feedback where submitted_at between ? and ?'
                .($aptIds ? ' and appointment_id in ('.$this->ph($aptIds).')' : '');
            $bindings = array_merge($bindings, [$period->from->utc(), $period->to->utc()], $aptIds);
        }

        if (empty($unionParts)) {
            return [];
        }

        $bucket = $period->bucketExpressionTz('submitted_at');
        $union = implode(' union all ', $unionParts);

        $sql = "with merged as ({$union})
                select {$bucket} as bucket, avg(rating::numeric) as avg_rating, count(*) as count
                from merged
                group by {$bucket}
                order by {$bucket}";

        return collect(DB::select($sql, $bindings))
            ->map(fn ($r) => [
                'bucket' => (string) $r->bucket,
                'avg_rating' => round((float) $r->avg_rating, 2),
                'count' => (int) $r->count,
            ])
            ->all();
    }

    private function lowRatingAlerts(AnalyticsScope $scope, array $filters): array
    {
        $threshold = (int) config('coms.analytics.low_rating_threshold', 2);
        $window = (int) config('coms.analytics.alert_window_days', 7);
        $limit = (int) config('coms.analytics.low_alert_limit', 20);
        $since = now()->subDays($window);

        [$onbIds, $aptIds] = $this->scopedIds(
            $scope,
            CarbonImmutable::now()->subDays($window),
            CarbonImmutable::now(),
            $filters,
        );

        $alerts = collect();

        // Onboarding-level feedback
        $onbRows = DB::table('onboarding_client_feedbacks as f')
            ->join('onboarding_requests as o', 'o.id', '=', 'f.onboarding_id')
            ->leftJoin('clients as c', 'c.id', '=', 'o.client_id')
            ->whereNull('o.deleted_at')
            ->where('f.rating', '<=', $threshold)
            ->where('f.submitted_at', '>=', $since)
            ->when(! empty($onbIds), fn ($q) => $q->whereIn('f.onboarding_id', $onbIds))
            ->when(empty($onbIds) && ! $scope->isAdmin(), fn ($q) => $q->whereRaw('1=0'))
            ->orderByDesc('f.submitted_at')
            ->limit($limit)
            ->get([
                'f.id as feedback_id',
                'f.onboarding_id',
                'f.rating',
                'f.comment',
                'f.submitted_at',
                'o.trainer_id',
                'c.company_name as client_name',
            ]);

        if ($onbRows->isNotEmpty()) {
            $onbIdList = $onbRows->pluck('onboarding_id')->all();
            $trainerMap = $this->attribution->bulkResolve($onbIdList, now());
            $trainerIds = array_values(array_filter($trainerMap));
            $names = DB::table('users')->whereIn('id', $trainerIds)->pluck(DB::raw("first_name || ' ' || last_name"), 'id')->all();

            foreach ($onbRows as $r) {
                $trainerId = $trainerMap[$r->onboarding_id] ?? $r->trainer_id;
                $alerts->push([
                    'source' => 'onboarding',
                    'feedback_id' => $r->feedback_id,
                    'onboarding_id' => $r->onboarding_id,
                    'appointment_id' => null,
                    'client_name' => $r->client_name,
                    'trainer_id' => $trainerId,
                    'trainer_name' => $trainerId ? ($names[$trainerId] ?? null) : null,
                    'rating' => (int) $r->rating,
                    'comment' => $r->comment,
                    'submitted_at' => (string) $r->submitted_at,
                ]);
            }
        }

        // Appointment-level feedback
        $aptRows = DB::table('appointment_feedback as f')
            ->join('appointments as a', 'a.id', '=', 'f.appointment_id')
            ->leftJoin('clients as c', 'c.id', '=', 'a.client_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.trainer_id')
            ->whereNull('a.deleted_at')
            ->where('f.rating', '<=', $threshold)
            ->where('f.submitted_at', '>=', $since)
            ->when(! empty($aptIds), fn ($q) => $q->whereIn('f.appointment_id', $aptIds))
            ->when(empty($aptIds) && ! $scope->isAdmin(), fn ($q) => $q->whereRaw('1=0'))
            ->orderByDesc('f.submitted_at')
            ->limit($limit)
            ->get([
                'f.id as feedback_id',
                'f.appointment_id',
                'f.rating',
                'f.comment',
                'f.submitted_at',
                'a.trainer_id',
                'c.company_name as client_name',
                DB::raw("u.first_name || ' ' || u.last_name as trainer_name"),
            ]);

        foreach ($aptRows as $r) {
            $alerts->push([
                'source' => 'appointment',
                'feedback_id' => $r->feedback_id,
                'onboarding_id' => null,
                'appointment_id' => $r->appointment_id,
                'client_name' => $r->client_name,
                'trainer_id' => $r->trainer_id,
                'trainer_name' => $r->trainer_name,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'submitted_at' => (string) $r->submitted_at,
            ]);
        }

        return $alerts->sortByDesc('submitted_at')->take($limit)->values()->all();
    }

    private function scopedIds(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
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

    private function ph(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}

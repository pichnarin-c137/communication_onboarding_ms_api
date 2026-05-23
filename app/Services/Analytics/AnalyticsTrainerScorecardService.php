<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenScopeException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use App\Services\Analytics\Support\KpiBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsTrainerScorecardService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $trainerId): array
    {
        $this->authorize($scope, $trainerId);

        $user = DB::table('users')->where('id', $trainerId)->first(['id', 'first_name', 'last_name']);
        if (! $user) {
            abort(404, 'TRAINER_NOT_FOUND');
        }

        $key = AnalyticsCacheKey::build("trainer:{$trainerId}", $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
            'compare' => $period->compareMode,
            'group_by' => $period->groupBy,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters, $trainerId, $user) {
            $cur = $this->snapshot($trainerId, $period->from, $period->to, $filters);
            $prev = ($period->compareFrom && $period->compareTo)
                ? $this->snapshot($trainerId, $period->compareFrom, $period->compareTo, $filters)
                : null;

            $kpis = [
                'completed_count'    => KpiBuilder::build($cur['done'], $prev['done'] ?? null, 'up'),
                'completion_rate'    => KpiBuilder::build($cur['completion_rate'], $prev['completion_rate'] ?? null, 'up'),
                'avg_rating'         => KpiBuilder::build($cur['avg_rating'], $prev['avg_rating'] ?? null, 'up'),
                'on_time_rate'       => KpiBuilder::build($cur['on_time_rate'], $prev['on_time_rate'] ?? null, 'up'),
                'active_onboardings' => KpiBuilder::build($cur['active_onboardings'], $prev['active_onboardings'] ?? null, 'up'),
            ];

            if ($scope->isTrainer()) {
                $kpis['lessons_sent'] = KpiBuilder::build($cur['lessons_sent'], $prev['lessons_sent'] ?? null, 'up');
            }

            return [
                'trainer' => [
                    'id' => $trainerId,
                    'full_name' => trim("{$user->first_name} {$user->last_name}"),
                    'avatar_url' => null,
                ],
                'kpis' => $kpis,
                'appointment_outcomes_by_week' => $this->outcomesByBucket($trainerId, $period, $filters),
                'rating_trend' => $this->ratingTrend($trainerId, $period),
                'recent_feedback' => $this->recentFeedback($trainerId, $period),
            ];
        });
    }

    private function authorize(AnalyticsScope $scope, string $trainerId): void
    {
        if ($scope->isAdmin()) {
            return;
        }

        if ($scope->isTrainer()) {
            if ($trainerId !== $scope->userId) {
                throw new ForbiddenScopeException(
                    'Trainers may only request their own scorecard.',
                    0, null, ['requested_trainer_id' => $trainerId],
                );
            }
            return;
        }

        if ($scope->isSale() && ! in_array($trainerId, $scope->trainerIds, true)) {
            throw new ForbiddenScopeException(
                'You cannot view analytics for a trainer outside your roster.',
                0, null, ['requested_trainer_id' => $trainerId],
            );
        }
    }

    private function snapshot(string $trainerId, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $threshold = (int) config('coms.analytics.on_time_threshold_min', 15);

        $apt = DB::table('appointments')
            ->whereNull('deleted_at')
            ->where('trainer_id', $trainerId)
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()]);
        AnalyticsFilters::applyAppointment($apt, $filters);

        $row = (array) $apt->selectRaw("
            count(*) as total,
            count(*) filter (where status = 'done') as done,
            count(*) filter (
                where status = 'done'
                  and actual_start_time is not null
                  and actual_start_time <= ((scheduled_date::timestamp + scheduled_start_time::time) + (interval '1 minute' * {$threshold}))
            ) as on_time
        ")->first();

        $total = (int) ($row['total'] ?? 0);
        $done = (int) ($row['done'] ?? 0);
        $onTime = (int) ($row['on_time'] ?? 0);

        $active = (int) DB::table('onboarding_requests')
            ->whereNull('deleted_at')
            ->where('trainer_id', $trainerId)
            ->whereIn('status', ['in_progress', 'on_hold', 'revision_requested'])
            ->count();

        // Avg rating across both feedback tables for this trainer
        $aptFb = DB::table('appointment_feedback as f')
            ->join('appointments as a', 'a.id', '=', 'f.appointment_id')
            ->where('a.trainer_id', $trainerId)
            ->whereBetween('f.submitted_at', [$from->utc(), $to->utc()])
            ->selectRaw('count(*) as c, avg(f.rating::numeric) as a')
            ->first();

        $onbFb = DB::table('onboarding_client_feedbacks as f')
            ->join('onboarding_requests as o', 'o.id', '=', 'f.onboarding_id')
            ->where('o.trainer_id', $trainerId)
            ->whereBetween('f.submitted_at', [$from->utc(), $to->utc()])
            ->selectRaw('count(*) as c, avg(f.rating::numeric) as a')
            ->first();

        $fbCount = (int) ($aptFb->c ?? 0) + (int) ($onbFb->c ?? 0);
        $fbSum = ((float) ($aptFb->a ?? 0)) * (int) ($aptFb->c ?? 0)
               + ((float) ($onbFb->a ?? 0)) * (int) ($onbFb->c ?? 0);
        $avgRating = $fbCount > 0 ? round($fbSum / $fbCount, 2) : null;

        $lessons = (int) DB::table('onboarding_lessons')
            ->whereNull('deleted_at')
            ->where('sent_by_user_id', $trainerId)
            ->whereBetween('sent_at', [$from->utc(), $to->utc()])
            ->count();

        return [
            'done' => $done,
            'completion_rate' => $total > 0 ? round($done / $total, 4) : 0.0,
            'on_time_rate' => $done > 0 ? round($onTime / $done, 4) : 0.0,
            'avg_rating' => $avgRating,
            'active_onboardings' => $active,
            'lessons_sent' => $lessons,
        ];
    }

    private function outcomesByBucket(string $trainerId, AnalyticsPeriod $period, array $filters): array
    {
        $q = DB::table('appointments')
            ->whereNull('deleted_at')
            ->where('trainer_id', $trainerId)
            ->whereBetween('scheduled_date', [$period->from->toDateString(), $period->to->toDateString()]);
        AnalyticsFilters::applyAppointment($q, $filters);

        $bucket = $period->bucketExpression('scheduled_date');

        return $q->selectRaw("
            {$bucket} as bucket,
            count(*) filter (where status = 'done') as done,
            count(*) filter (where status = 'cancelled') as cancelled,
            count(*) filter (where status = 'rescheduled') as rescheduled,
            count(*) filter (where status = 'done' and student_count = 0) as no_show
        ")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get()
            ->map(fn ($r) => [
                'bucket' => (string) $r->bucket,
                'done' => (int) $r->done,
                'cancelled' => (int) $r->cancelled,
                'rescheduled' => (int) $r->rescheduled,
                'no_show' => (int) $r->no_show,
            ])
            ->all();
    }

    private function ratingTrend(string $trainerId, AnalyticsPeriod $period): array
    {
        $bucket = $period->bucketExpressionTz('submitted_at');

        $sql = "with merged as (
            select f.submitted_at, f.rating from appointment_feedback f
            join appointments a on a.id = f.appointment_id
            where a.trainer_id = ? and f.submitted_at between ? and ?
            union all
            select f.submitted_at, f.rating from onboarding_client_feedbacks f
            join onboarding_requests o on o.id = f.onboarding_id
            where o.trainer_id = ? and f.submitted_at between ? and ?
        )
        select {$bucket} as bucket, avg(rating::numeric) as avg_rating, count(*) as count
        from merged
        group by {$bucket}
        order by {$bucket}";

        return collect(DB::select($sql, [
            $trainerId, $period->from->utc(), $period->to->utc(),
            $trainerId, $period->from->utc(), $period->to->utc(),
        ]))->map(fn ($r) => [
            'bucket' => (string) $r->bucket,
            'avg_rating' => round((float) $r->avg_rating, 2),
            'count' => (int) $r->count,
        ])->all();
    }

    private function recentFeedback(string $trainerId, AnalyticsPeriod $period): array
    {
        $sql = "with merged as (
            select f.rating, f.comment, f.submitted_at, c.company_name as client_name
            from appointment_feedback f
            join appointments a on a.id = f.appointment_id
            left join clients c on c.id = a.client_id
            where a.trainer_id = ? and f.submitted_at between ? and ?
            union all
            select f.rating, f.comment, f.submitted_at, c.company_name as client_name
            from onboarding_client_feedbacks f
            join onboarding_requests o on o.id = f.onboarding_id
            left join clients c on c.id = o.client_id
            where o.trainer_id = ? and f.submitted_at between ? and ?
        )
        select * from merged order by submitted_at desc limit 10";

        return collect(DB::select($sql, [
            $trainerId, $period->from->utc(), $period->to->utc(),
            $trainerId, $period->from->utc(), $period->to->utc(),
        ]))->map(fn ($r) => [
            'rating' => (int) $r->rating,
            'comment' => $r->comment,
            'client_name' => $r->client_name,
            'submitted_at' => (string) $r->submitted_at,
        ])->all();
    }
}

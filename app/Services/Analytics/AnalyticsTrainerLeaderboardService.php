<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenRoleException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Illuminate\Support\Facades\DB;

class AnalyticsTrainerLeaderboardService
{
    public const ALLOWED_SORT = [
        'completion_rate', 'avg_rating', 'completed_count', 'workload_pct', 'on_time_rate',
    ];

    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $sort, string $order, int $page, int $perPage): array
    {
        if ($scope->isTrainer()) {
            throw new ForbiddenRoleException('Trainers cannot view the leaderboard.');
        }
        if (! in_array($sort, self::ALLOWED_SORT, true)) {
            $sort = 'completion_rate';
        }
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $key = AnalyticsCacheKey::build('trainers_leaderboard', $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
            'sort' => $sort, 'order' => $order, 'page' => $page, 'per_page' => $perPage,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters, $sort, $order, $page, $perPage) {
            // 1) Resolve candidate trainer ids based on scope
            $trainerIds = $this->candidateTrainers($scope);

            // 2) Aggregate per-trainer KPIs in one go
            $aggregates = $this->aggregate($trainerIds, $period, $filters);

            // 3) Merge with user records for display
            $users = DB::table('users')
                ->whereIn('id', $trainerIds)
                ->whereNull('deleted_at')
                ->get(['id', 'first_name', 'last_name'])
                ->keyBy('id');

            $maxRoster = max((int) config('coms.sale_roster.max_concurrent_active_onboardings_per_trainer', 5), 1);

            $rows = collect($trainerIds)
                ->map(function ($tid) use ($aggregates, $users, $maxRoster) {
                    $agg = $aggregates[$tid] ?? [];
                    $u = $users[$tid] ?? null;
                    $done = (int) ($agg['done'] ?? 0);
                    $total = (int) ($agg['total'] ?? 0);
                    $onTime = (int) ($agg['on_time'] ?? 0);

                    return [
                        'trainer_user_id' => $tid,
                        'full_name' => $u ? trim("{$u->first_name} {$u->last_name}") : null,
                        'avatar_url' => null,
                        'completed_count'   => $done,
                        'cancelled_count'   => (int) ($agg['cancelled'] ?? 0),
                        'rescheduled_count' => (int) ($agg['rescheduled'] ?? 0),
                        'no_show_count'     => (int) ($agg['no_show'] ?? 0),
                        'completion_rate'   => $total > 0 ? round($done / $total, 4) : 0.0,
                        'on_time_rate'      => $done > 0 ? round($onTime / $done, 4) : 0.0,
                        'avg_rating'        => isset($agg['avg_rating']) ? round((float) $agg['avg_rating'], 2) : null,
                        'feedback_count'    => (int) ($agg['feedback_count'] ?? 0),
                        'active_onboardings' => (int) ($agg['active_onboardings'] ?? 0),
                        'workload_pct'      => round(min(1.0, ((int) ($agg['active_onboardings'] ?? 0)) / $maxRoster), 4),
                        'lessons_sent'      => (int) ($agg['lessons_sent'] ?? 0),
                    ];
                });

            $sorted = $rows->sortBy(fn ($r) => $r[$sort] ?? 0, SORT_REGULAR, $order === 'desc')->values();

            $total = $sorted->count();
            $offset = ($page - 1) * $perPage;
            $paginated = $sorted->slice($offset, $perPage)->values();

            return [
                'rows' => $paginated->all(),
                'meta' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                    'from' => $total === 0 ? 0 : $offset + 1,
                    'to'   => $total === 0 ? 0 : min($offset + $perPage, $total),
                ],
            ];
        });
    }

    private function candidateTrainers(AnalyticsScope $scope): array
    {
        if ($scope->overrideTrainerId !== null) {
            return [$scope->overrideTrainerId];
        }

        if ($scope->isAdmin()) {
            return DB::table('users as u')
                ->join('roles as r', 'r.id', '=', 'u.role_id')
                ->whereNull('u.deleted_at')
                ->where('r.role', 'trainer')
                ->pluck('u.id')->all();
        }

        // Sale
        return $scope->trainerIds;
    }

    private function aggregate(array $trainerIds, AnalyticsPeriod $period, array $filters): array
    {
        if (empty($trainerIds)) {
            return [];
        }

        $threshold = (int) config('coms.analytics.on_time_threshold_min', 15);

        $apt = DB::table('appointments')
            ->whereNull('deleted_at')
            ->whereIn('trainer_id', $trainerIds)
            ->whereBetween('scheduled_date', [$period->from->toDateString(), $period->to->toDateString()]);
        AnalyticsFilters::applyAppointment($apt, $filters);

        $aptRows = $apt->selectRaw("
            trainer_id,
            count(*) as total,
            count(*) filter (where status = 'done') as done,
            count(*) filter (where status = 'cancelled') as cancelled,
            count(*) filter (where status = 'rescheduled') as rescheduled,
            count(*) filter (where status = 'done' and student_count = 0) as no_show,
            count(*) filter (
                where status = 'done'
                  and actual_start_time is not null
                  and actual_start_time <= ((scheduled_date::timestamp + scheduled_start_time::time) + (interval '1 minute' * {$threshold}))
            ) as on_time
        ")->groupBy('trainer_id')->get();

        // Active onboardings (current)
        $activeRows = DB::table('onboarding_requests')
            ->whereNull('deleted_at')
            ->whereIn('trainer_id', $trainerIds)
            ->whereIn('status', ['in_progress', 'on_hold', 'revision_requested'])
            ->selectRaw('trainer_id, count(*) as c')
            ->groupBy('trainer_id')
            ->get();

        // Feedback: appointment-feedback by appointment.trainer_id
        $fbAptRows = DB::table('appointment_feedback as f')
            ->join('appointments as a', 'a.id', '=', 'f.appointment_id')
            ->whereNull('a.deleted_at')
            ->whereIn('a.trainer_id', $trainerIds)
            ->whereBetween('f.submitted_at', [$period->from->utc(), $period->to->utc()])
            ->selectRaw('a.trainer_id as trainer_id, count(*) as count, avg(f.rating::numeric) as avg_rating')
            ->groupBy('a.trainer_id')
            ->get();

        // Feedback: onboarding-feedback by onboarding_requests.trainer_id (simpler than attribution here)
        $fbOnbRows = DB::table('onboarding_client_feedbacks as f')
            ->join('onboarding_requests as o', 'o.id', '=', 'f.onboarding_id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.trainer_id', $trainerIds)
            ->whereBetween('f.submitted_at', [$period->from->utc(), $period->to->utc()])
            ->selectRaw('o.trainer_id as trainer_id, count(*) as count, avg(f.rating::numeric) as avg_rating')
            ->groupBy('o.trainer_id')
            ->get();

        // Lessons sent in period
        $lessonRows = DB::table('onboarding_lessons')
            ->whereNull('deleted_at')
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$period->from->utc(), $period->to->utc()])
            ->whereIn('sent_by_user_id', $trainerIds)
            ->selectRaw('sent_by_user_id, count(*) as c')
            ->groupBy('sent_by_user_id')
            ->get();

        $out = [];
        foreach ($trainerIds as $tid) {
            $out[$tid] = [
                'total' => 0, 'done' => 0, 'cancelled' => 0, 'rescheduled' => 0, 'no_show' => 0, 'on_time' => 0,
                'active_onboardings' => 0, 'feedback_count' => 0, 'avg_rating' => null, 'lessons_sent' => 0,
            ];
        }

        foreach ($aptRows as $r) {
            $out[$r->trainer_id] = array_merge($out[$r->trainer_id], [
                'total' => (int) $r->total, 'done' => (int) $r->done,
                'cancelled' => (int) $r->cancelled, 'rescheduled' => (int) $r->rescheduled,
                'no_show' => (int) $r->no_show, 'on_time' => (int) $r->on_time,
            ]);
        }
        foreach ($activeRows as $r) {
            $out[$r->trainer_id]['active_onboardings'] = (int) $r->c;
        }
        foreach ($lessonRows as $r) {
            $out[$r->sent_by_user_id]['lessons_sent'] = (int) $r->c;
        }

        // Merge feedback across the two sources
        $fbByTrainer = [];
        foreach ([$fbAptRows, $fbOnbRows] as $set) {
            foreach ($set as $r) {
                $tid = $r->trainer_id;
                if (! isset($fbByTrainer[$tid])) {
                    $fbByTrainer[$tid] = ['count' => 0, 'sum' => 0.0];
                }
                $fbByTrainer[$tid]['count'] += (int) $r->count;
                $fbByTrainer[$tid]['sum']   += ((float) $r->avg_rating) * ((int) $r->count);
            }
        }
        foreach ($fbByTrainer as $tid => $agg) {
            $out[$tid]['feedback_count'] = $agg['count'];
            $out[$tid]['avg_rating'] = $agg['count'] > 0 ? $agg['sum'] / $agg['count'] : null;
        }

        return $out;
    }
}

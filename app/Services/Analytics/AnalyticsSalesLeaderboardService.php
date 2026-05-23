<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenRoleException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Illuminate\Support\Facades\DB;

class AnalyticsSalesLeaderboardService
{
    public const ALLOWED_SORT = [
        'appointments_created', 'onboardings_completed', 'conversion_pct', 'roster_utilization_pct', 'roster_avg_rating',
    ];

    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters, string $sort, string $order, int $page, int $perPage): array
    {
        if (! $scope->isAdmin()) {
            throw new ForbiddenRoleException('Only admins can view the sales leaderboard.');
        }
        if (! in_array($sort, self::ALLOWED_SORT, true)) {
            $sort = 'appointments_created';
        }
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $key = AnalyticsCacheKey::build('sales_leaderboard', $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
            'sort' => $sort, 'order' => $order, 'page' => $page, 'per_page' => $perPage,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($period, $filters, $sort, $order, $page, $perPage) {
            // All sale users
            $saleIds = DB::table('users as u')
                ->join('roles as r', 'r.id', '=', 'u.role_id')
                ->whereNull('u.deleted_at')
                ->where('r.role', 'sale')
                ->pluck('u.id')->all();

            if (empty($saleIds)) {
                return ['rows' => [], 'meta' => $this->emptyMeta($perPage)];
            }

            // Appointments created per sale
            $apt = DB::table('appointments')
                ->whereNull('deleted_at')
                ->whereIn('creator_id', $saleIds)
                ->whereBetween('created_at', [$period->from->utc(), $period->to->utc()]);
            AnalyticsFilters::applyAppointment($apt, $filters);

            $aptCounts = $apt->selectRaw('creator_id, count(*) as c')->groupBy('creator_id')->pluck('c', 'creator_id')->all();

            // Onboardings completed per sale (via appointment.creator_id)
            $completed = DB::table('onboarding_requests as o')
                ->join('appointments as a', 'a.id', '=', 'o.appointment_id')
                ->whereNull('o.deleted_at')
                ->whereNull('a.deleted_at')
                ->whereIn('a.creator_id', $saleIds)
                ->where('o.status', 'completed')
                ->whereBetween('o.completed_at', [$period->from->utc(), $period->to->utc()])
                ->selectRaw('a.creator_id as sale_id, count(*) as c')
                ->groupBy('a.creator_id')
                ->pluck('c', 'sale_id')
                ->all();

            // Onboardings started per sale
            $started = DB::table('onboarding_requests as o')
                ->join('appointments as a', 'a.id', '=', 'o.appointment_id')
                ->whereNull('o.deleted_at')
                ->whereNull('a.deleted_at')
                ->whereIn('a.creator_id', $saleIds)
                ->whereBetween('o.created_at', [$period->from->utc(), $period->to->utc()])
                ->selectRaw('a.creator_id as sale_id, count(*) as c')
                ->groupBy('a.creator_id')
                ->pluck('c', 'sale_id')
                ->all();

            // Avg time to schedule = appointment.created_at → scheduled_date
            $avgLead = DB::table('appointments')
                ->whereNull('deleted_at')
                ->whereIn('creator_id', $saleIds)
                ->whereBetween('created_at', [$period->from->utc(), $period->to->utc()])
                ->selectRaw('creator_id, avg(extract(epoch from (scheduled_date::timestamp - created_at)) / 86400.0) as d')
                ->groupBy('creator_id')
                ->pluck('d', 'creator_id')
                ->all();

            // Roster info from sale_trainer_assignments + per-trainer active onboardings + avg rating
            $rosters = DB::table('sale_trainer_assignments')
                ->whereNull('deleted_at')
                ->whereIn('sale_user_id', $saleIds)
                ->get(['sale_user_id', 'trainer_user_id'])
                ->groupBy('sale_user_id');

            $allTrainerIds = $rosters->flatten(1)->pluck('trainer_user_id')->unique()->values()->all();

            $activeByTrainer = DB::table('onboarding_requests')
                ->whereNull('deleted_at')
                ->whereIn('trainer_id', $allTrainerIds)
                ->whereIn('status', ['in_progress', 'on_hold', 'revision_requested'])
                ->selectRaw('trainer_id, count(*) as c')
                ->groupBy('trainer_id')
                ->pluck('c', 'trainer_id')
                ->all();

            $ratingByTrainer = DB::table('onboarding_client_feedbacks as f')
                ->join('onboarding_requests as o', 'o.id', '=', 'f.onboarding_id')
                ->whereIn('o.trainer_id', $allTrainerIds)
                ->whereBetween('f.submitted_at', [$period->from->utc(), $period->to->utc()])
                ->selectRaw('o.trainer_id as trainer_id, count(*) as c, avg(f.rating::numeric) as a')
                ->groupBy('o.trainer_id')
                ->get()
                ->keyBy('trainer_id');

            $maxRoster = max((int) config('coms.sale_roster.max_concurrent_active_onboardings_per_trainer', 5), 1);

            $users = DB::table('users')->whereIn('id', $saleIds)->get(['id', 'first_name', 'last_name'])->keyBy('id');

            $rows = collect($saleIds)->map(function ($sid) use ($aptCounts, $completed, $started, $avgLead, $rosters, $activeByTrainer, $ratingByTrainer, $maxRoster, $users) {
                $u = $users[$sid] ?? null;
                $aptCount = (int) ($aptCounts[$sid] ?? 0);
                $compCount = (int) ($completed[$sid] ?? 0);
                $startCount = (int) ($started[$sid] ?? 0);

                $roster = $rosters[$sid] ?? collect();
                $rosterSize = $roster->count();
                $ratingSum = 0.0;
                $ratingCnt = 0;
                $activeTotal = 0;
                foreach ($roster as $r) {
                    $activeTotal += (int) ($activeByTrainer[$r->trainer_user_id] ?? 0);
                    if (isset($ratingByTrainer[$r->trainer_user_id])) {
                        $row = $ratingByTrainer[$r->trainer_user_id];
                        $ratingSum += ((float) $row->a) * ((int) $row->c);
                        $ratingCnt += (int) $row->c;
                    }
                }

                return [
                    'sale_user_id' => $sid,
                    'full_name' => $u ? trim("{$u->first_name} {$u->last_name}") : null,
                    'appointments_created' => $aptCount,
                    'onboardings_started' => $startCount,
                    'onboardings_completed' => $compCount,
                    'conversion_pct' => $aptCount > 0 ? round($compCount / $aptCount, 4) : 0.0,
                    'avg_time_to_schedule_days' => isset($avgLead[$sid]) ? round((float) $avgLead[$sid], 1) : null,
                    'roster_size' => $rosterSize,
                    'roster_avg_rating' => $ratingCnt > 0 ? round($ratingSum / $ratingCnt, 2) : null,
                    'roster_utilization_pct' => $rosterSize > 0 ? round(min(1.0, $activeTotal / ($rosterSize * $maxRoster)), 4) : 0.0,
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

    private function emptyMeta(int $perPage): array
    {
        return ['total' => 0, 'per_page' => $perPage, 'current_page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
    }
}

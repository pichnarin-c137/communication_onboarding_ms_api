<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenRoleException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Illuminate\Support\Facades\DB;

class AnalyticsOnboardingBreakdownService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        if ($scope->isTrainer()) {
            throw new ForbiddenRoleException('Trainers cannot view onboarding breakdown.');
        }

        $key = AnalyticsCacheKey::build('onboardings_breakdown', $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period) {
            $q = DB::table('onboarding_requests as o')->whereNull('o.deleted_at')
                ->whereBetween('o.created_at', [$period->from->utc(), $period->to->utc()]);
            $scope->applyOnboardingScope($q, 'o');

            $row = (array) (clone $q)->selectRaw('
                count(*) as started,
                count(*) filter (where o.status = ?) as completed,
                count(*) filter (where o.status = ?) as cancelled,
                count(*) filter (where o.status = ?) as on_hold_now,
                count(*) filter (where o.hold_count > 0) as ever_held,
                count(*) filter (where o.revision_note is not null or o.revision_requested_at is not null) as ever_revised,
                count(*) filter (where o.cycle_number > 1) as ever_reassigned
            ', ['completed', 'cancelled', 'on_hold'])->first();

            $started = (int) ($row['started'] ?? 0);
            $completed = (int) ($row['completed'] ?? 0);
            $heldEver = (int) ($row['ever_held'] ?? 0);
            $revisedEver = (int) ($row['ever_revised'] ?? 0);
            $reassignedEver = (int) ($row['ever_reassigned'] ?? 0);

            $totals = [
                'started'         => $started,
                'completed'       => $completed,
                'cancelled'       => (int) ($row['cancelled'] ?? 0),
                'on_hold_now'     => (int) ($row['on_hold_now'] ?? 0),
                'ever_held'       => $heldEver,
                'ever_revised'    => $revisedEver,
                'ever_reassigned' => $reassignedEver,
            ];

            $rates = [
                'completion'        => $started > 0 ? round($completed / $started, 4) : 0.0,
                'hold_rate'         => $started > 0 ? round($heldEver / $started, 4) : 0.0,
                'revision_rate'     => $started > 0 ? round($revisedEver / $started, 4) : 0.0,
                'reassignment_rate' => $started > 0 ? round($reassignedEver / $started, 4) : 0.0,
            ];

            $cycleDist = (clone $q)
                ->selectRaw('o.cycle_number as cycle, count(*) as c')
                ->groupBy('o.cycle_number')
                ->orderBy('o.cycle_number')
                ->get()
                ->map(fn ($r) => ['cycle' => (int) $r->cycle, 'count' => (int) $r->c])
                ->all();

            $timeInStage = $this->timeInStageAverages($scope, $period);

            return [
                'totals' => $totals,
                'rates' => $rates,
                'cycle_distribution' => $cycleDist,
                'avg_time_in_stage_hours' => $timeInStage,
            ];
        });
    }

    /**
     * For each onboarding row in the scoped+period set, compute the duration
     * each status was held, then average per status.
     */
    private function timeInStageAverages(AnalyticsScope $scope, AnalyticsPeriod $period): ?array
    {
        $q = DB::table('onboarding_requests as o')->whereNull('o.deleted_at')
            ->whereBetween('o.created_at', [$period->from->utc(), $period->to->utc()]);
        $scope->applyOnboardingScope($q, 'o');

        $onbIds = $q->pluck('o.id')->all();
        if (empty($onbIds)) {
            return null;
        }

        $sql = "with ranked as (
                  select onboarding_id, to_status, changed_at,
                         lead(changed_at) over (partition by onboarding_id order by changed_at) as next_changed_at
                  from onboarding_status_history
                  where onboarding_id = any(?::uuid[])
                )
                select to_status,
                       avg(extract(epoch from (coalesce(next_changed_at, now()) - changed_at)) / 3600.0) as avg_h
                from ranked
                group by to_status";

        $rows = DB::select($sql, [$this->pgArray($onbIds)]);

        $out = [];
        foreach ($rows as $r) {
            $out[$r->to_status] = round((float) $r->avg_h, 1);
        }
        return $out;
    }

    private function pgArray(array $uuids): string
    {
        if (empty($uuids)) {
            return '{}';
        }
        return '{'.implode(',', $uuids).'}';
    }
}

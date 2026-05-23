<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use App\Services\Analytics\Support\KpiBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsAppointmentService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        $key = AnalyticsCacheKey::build('appointments', $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
            'compare' => $period->compareMode,
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters) {
            $cur = $this->snapshot($scope, $period->from, $period->to, $filters);
            $prev = ($period->compareFrom && $period->compareTo)
                ? $this->snapshot($scope, $period->compareFrom, $period->compareTo, $filters)
                : null;

            $cur['on_time_rate'] = KpiBuilder::build(
                $cur['_on_time'],
                $prev ? $prev['_on_time'] : null,
                'up',
            );
            unset($cur['_on_time']);

            $cur['demo_to_training_conversion'] = $this->demoToTrainingConversion($scope, $period->from, $period->to, $filters);

            return $cur;
        });
    }

    private function snapshot(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $q = DB::table('appointments')
            ->whereNull('appointments.deleted_at')
            ->whereBetween('appointments.scheduled_date', [$from->toDateString(), $to->toDateString()]);

        $scope->applyAppointmentScope($q);
        AnalyticsFilters::applyAppointment($q, $filters);

        $threshold = (int) config('coms.analytics.on_time_threshold_min', 15);

        $row = (array) (clone $q)->selectRaw("
            count(*) as total,
            count(*) filter (where status = 'pending')      as pending,
            count(*) filter (where status = 'leave_office') as leave_office,
            count(*) filter (where status = 'in_progress')  as in_progress,
            count(*) filter (where status = 'done')         as done,
            count(*) filter (where status = 'cancelled')    as cancelled,
            count(*) filter (where status = 'rescheduled')  as rescheduled,
            count(*) filter (where status = 'done' and student_count = 0) as no_show,
            count(*) filter (where appointment_type = 'demo')     as demo,
            count(*) filter (where appointment_type = 'training') as training,
            count(*) filter (where location_type = 'online')   as loc_online,
            count(*) filter (where location_type = 'physical') as loc_physical,
            count(*) filter (where location_type = 'hybrid')   as loc_hybrid,
            count(*) filter (
                where status = 'done'
                  and actual_start_time is not null
                  and actual_start_time <= ((scheduled_date::timestamp + scheduled_start_time::time) + (interval '1 minute' * {$threshold}))
            ) as on_time_started,
            avg(extract(epoch from (actual_end_time - actual_start_time)) / 60.0)
                filter (where status = 'done' and actual_start_time is not null and actual_end_time is not null) as avg_duration_min,
            avg(extract(epoch from (scheduled_date::timestamp - created_at)) / 86400.0) as avg_lead_days
        ")->first();

        $total = (int) ($row['total'] ?? 0);
        $done = (int) ($row['done'] ?? 0);

        $pct = fn (int $count) => $total > 0 ? round(($count / $total) * 100, 1) : 0.0;

        $byStatus = [];
        foreach (['pending', 'leave_office', 'in_progress', 'done', 'cancelled', 'rescheduled'] as $s) {
            $c = (int) ($row[$s] ?? 0);
            $byStatus[$s] = ['count' => $c, 'pct' => $pct($c)];
        }

        $byType = [];
        foreach (['demo', 'training'] as $t) {
            $c = (int) ($row[$t] ?? 0);
            $byType[$t] = ['count' => $c, 'pct' => $pct($c)];
        }

        $byLocation = [];
        foreach (['online', 'physical', 'hybrid'] as $l) {
            $c = (int) ($row["loc_{$l}"] ?? 0);
            $byLocation[$l] = ['count' => $c, 'pct' => $pct($c)];
        }

        return [
            'totals' => [
                'total' => $total,
                'completed' => $done,
                'cancelled' => (int) ($row['cancelled'] ?? 0),
                'rescheduled' => (int) ($row['rescheduled'] ?? 0),
                'no_show' => (int) ($row['no_show'] ?? 0),
            ],
            'by_status'   => $byStatus,
            'by_type'     => $byType,
            'by_location' => $byLocation,
            'avg_session_duration_min' => isset($row['avg_duration_min']) ? (int) round((float) $row['avg_duration_min']) : 0,
            'avg_lead_time_days'       => isset($row['avg_lead_days']) ? round((float) $row['avg_lead_days'], 1) : 0.0,
            '_on_time' => $done > 0 ? round(((int) $row['on_time_started']) / $done, 4) : 0.0,
        ];
    }

    private function demoToTrainingConversion(AnalyticsScope $scope, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $window = (int) config('coms.analytics.demo_to_training_window_days', 30);

        $demoQ = DB::table('appointments as a')
            ->whereNull('a.deleted_at')
            ->where('a.appointment_type', 'demo')
            ->where('a.status', 'done')
            ->whereBetween('a.scheduled_date', [$from->toDateString(), $to->toDateString()]);

        $scope->applyAppointmentScope($demoQ, 'a');
        AnalyticsFilters::applyAppointment($demoQ, $filters);

        $demos = (clone $demoQ)->select('a.client_id', 'a.scheduled_date')->distinct()->get();

        if ($demos->isEmpty()) {
            return ['demos' => 0, 'demos_with_training' => 0, 'rate' => 0.0];
        }

        $clientIds = $demos->pluck('client_id')->unique()->all();
        $trainings = DB::table('appointments')
            ->whereNull('deleted_at')
            ->where('appointment_type', 'training')
            ->whereIn('client_id', $clientIds)
            ->whereBetween('created_at', [$from->utc(), $to->copy()->addDays($window)->utc()])
            ->get(['client_id', 'created_at']);

        $withTraining = 0;
        foreach ($demos as $d) {
            $cutoff = strtotime($d->scheduled_date . ' +' . $window . ' days');
            $hit = $trainings
                ->where('client_id', $d->client_id)
                ->first(fn ($t) => strtotime((string) $t->created_at) <= $cutoff
                                    && strtotime((string) $t->created_at) >= strtotime((string) $d->scheduled_date));
            if ($hit) {
                $withTraining++;
            }
        }

        $count = $demos->count();
        return [
            'demos' => $count,
            'demos_with_training' => $withTraining,
            'rate' => $count > 0 ? round($withTraining / $count, 4) : 0.0,
        ];
    }
}

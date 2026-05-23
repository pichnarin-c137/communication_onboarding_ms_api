<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenRoleException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Illuminate\Support\Facades\DB;

class AnalyticsHeatmapService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        if (! $scope->isAdmin()) {
            throw new ForbiddenRoleException('Only admins can view the heatmap.');
        }

        $key = AnalyticsCacheKey::build('heatmap', $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period, $filters) {
            $tz = $period->timezone;

            $q = DB::table('appointments')
                ->whereNull('deleted_at')
                ->whereBetween('scheduled_date', [$period->from->toDateString(), $period->to->toDateString()]);
            $scope->applyAppointmentScope($q);
            AnalyticsFilters::applyAppointment($q, $filters);

            $rows = $q->selectRaw("
                (extract(isodow from scheduled_date) - 1)::int as weekday,
                extract(hour from scheduled_start_time::time)::int as hour,
                count(*) as count
            ")
                ->groupByRaw('1, 2')
                ->orderByRaw('1, 2')
                ->get();

            $cells = $rows
                ->filter(fn ($r) => (int) $r->count > 0)
                ->map(fn ($r) => [
                    'weekday' => (int) $r->weekday,
                    'hour' => (int) $r->hour,
                    'count' => (int) $r->count,
                ])
                ->values();

            $maxCount = $cells->max('count') ?? 0;
            $total = (int) $cells->sum('count');

            return [
                'cells' => $cells->all(),
                'max_count' => (int) $maxCount,
                'total' => $total,
            ];
        });
    }
}

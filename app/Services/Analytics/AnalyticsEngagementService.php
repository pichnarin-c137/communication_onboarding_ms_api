<?php

namespace App\Services\Analytics;

use App\Exceptions\Analytics\ForbiddenRoleException;
use App\Services\Analytics\Support\AnalyticsCache;
use App\Services\Analytics\Support\AnalyticsCacheKey;
use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Illuminate\Support\Facades\DB;

class AnalyticsEngagementService
{
    public function __construct(private AnalyticsCache $cache) {}

    public function compute(AnalyticsScope $scope, AnalyticsPeriod $period, array $filters): array
    {
        if ($scope->isTrainer()) {
            throw new ForbiddenRoleException('Trainers cannot view engagement analytics.');
        }

        $key = AnalyticsCacheKey::build('engagement', $scope, $filters + [
            'from' => $period->from->toDateString(),
            'to'   => $period->to->toDateString(),
        ]);

        return $this->cache->remember($key, $scope, (int) config('coms.analytics.cache_ttl', 300), function () use ($scope, $period) {
            // Telegram messages aggregated over [from..to] by sent_at (or created_at if not sent).
            $tg = DB::table('telegram_messages')
                ->where(function ($q) use ($period) {
                    $q->whereBetween('sent_at', [$period->from->utc(), $period->to->utc()])
                      ->orWhereBetween('created_at', [$period->from->utc(), $period->to->utc()]);
                });

            $row = (array) (clone $tg)->selectRaw("
                count(*) filter (where status in ('sent', 'delivered')) as sent,
                count(*) filter (where status = 'failed') as failed
            ")->first();

            $sent = (int) ($row['sent'] ?? 0);
            $failed = (int) ($row['failed'] ?? 0);

            $byType = (clone $tg)
                ->selectRaw('message_type, count(*) as c')
                ->groupBy('message_type')
                ->pluck('c', 'message_type')
                ->all();

            $groupsActive = (int) DB::table('telegram_groups')
                ->whereNull('deleted_at')
                ->whereIn('bot_status', ['connected', 'reconnected'])
                ->count();

            $groupsRemoved = (int) DB::table('telegram_groups')
                ->whereNull('deleted_at')
                ->where('bot_status', 'removed')
                ->count();

            // Lessons sent during the period — apply onboarding scope via join
            $lesQ = DB::table('onboarding_lessons as l')
                ->join('onboarding_requests as o', 'o.id', '=', 'l.onboarding_id')
                ->whereNull('l.deleted_at')
                ->whereNull('o.deleted_at')
                ->whereNotNull('l.sent_at')
                ->whereBetween('l.sent_at', [$period->from->utc(), $period->to->utc()]);

            $scope->applyOnboardingScope($lesQ, 'o');

            $lessonsSent = (int) (clone $lesQ)->count();

            // Per-onboarding average (lessons sent / distinct onboardings touched)
            $distinctOnb = (int) (clone $lesQ)->distinct()->count('o.id');
            $perOnbAvg = $distinctOnb > 0 ? round($lessonsSent / $distinctOnb, 2) : 0.0;

            $byPath = (clone $lesQ)
                ->selectRaw("'path_' || l.path::text as p, count(*) as c")
                ->groupByRaw("'path_' || l.path::text")
                ->pluck('c', 'p')
                ->all();

            return [
                'telegram' => [
                    'messages_sent' => $sent,
                    'messages_failed' => $failed,
                    'delivery_rate' => ($sent + $failed) > 0 ? round($sent / ($sent + $failed), 4) : 0.0,
                    'groups_active' => $groupsActive,
                    'groups_removed' => $groupsRemoved,
                    'by_type' => array_map(fn ($v) => (int) $v, $byType),
                ],
                'lessons' => [
                    'sent' => $lessonsSent,
                    'per_onboarding_avg' => $perOnbAvg,
                    'by_path' => array_map(fn ($v) => (int) $v, $byPath),
                ],
            ];
        });
    }
}

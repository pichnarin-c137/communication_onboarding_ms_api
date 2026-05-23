<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Http\Requests\Analytics\AnalyticsLeaderboardRequest;
use App\Services\Analytics\AnalyticsTrainerLeaderboardService;
use App\Services\Analytics\AnalyticsTrainerScorecardService;
use App\Services\Analytics\Support\AnalyticsPeriod;
use Illuminate\Http\JsonResponse;

class AnalyticsTrainerController extends Controller
{
    use AnalyticsResponder;

    public function __construct(
        private AnalyticsTrainerLeaderboardService $leaderboard,
        private AnalyticsTrainerScorecardService $scorecard,
    ) {}

    public function leaderboard(AnalyticsLeaderboardRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');
        $filters = $this->filters($request);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $sort = (string) ($request->query('sort') ?? 'completion_rate');
        $order = (string) ($request->query('order') ?? 'desc');

        $data = $this->leaderboard->compute($scope, $period, $filters, $sort, $order, $page, $perPage);

        return $this->analyticsResponse($request, $period, $data);
    }

    public function scorecard(AnalyticsFilterRequest $request, string $id): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');
        $filters = $this->filters($request);

        $data = $this->scorecard->compute($scope, $period, $filters, $id);

        return $this->analyticsResponse($request, $period, $data);
    }

    private function filters($request): array
    {
        return array_filter([
            'business_type_id' => $request->query('business_type_id'),
            'system_id'        => $request->query('system_id'),
            'location_type'    => $request->query('location_type'),
        ], fn ($v) => $v !== null && $v !== '');
    }
}

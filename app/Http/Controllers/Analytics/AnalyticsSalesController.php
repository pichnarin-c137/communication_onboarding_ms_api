<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsLeaderboardRequest;
use App\Services\Analytics\AnalyticsSalesLeaderboardService;
use App\Services\Analytics\Support\AnalyticsPeriod;
use Illuminate\Http\JsonResponse;

class AnalyticsSalesController extends Controller
{
    use AnalyticsResponder;

    public function __construct(private AnalyticsSalesLeaderboardService $service) {}

    public function leaderboard(AnalyticsLeaderboardRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');
        $filters = array_filter([
            'business_type_id' => $request->query('business_type_id'),
            'system_id'        => $request->query('system_id'),
            'location_type'    => $request->query('location_type'),
        ], fn ($v) => $v !== null && $v !== '');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $sort = (string) ($request->query('sort') ?? 'appointments_created');
        $order = (string) ($request->query('order') ?? 'desc');

        $data = $this->service->compute($scope, $period, $filters, $sort, $order, $page, $perPage);

        return $this->analyticsResponse($request, $period, $data);
    }
}

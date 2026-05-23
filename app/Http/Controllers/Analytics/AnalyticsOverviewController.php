<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Http\Requests\Analytics\AnalyticsTrendsRequest;
use App\Services\Analytics\AnalyticsOverviewService;
use App\Services\Analytics\AnalyticsTrendsService;
use App\Services\Analytics\Support\AnalyticsPeriod;
use Illuminate\Http\JsonResponse;

class AnalyticsOverviewController extends Controller
{
    use AnalyticsResponder;

    public function __construct(
        private AnalyticsOverviewService $overview,
        private AnalyticsTrendsService $trends,
    ) {}

    public function overview(AnalyticsFilterRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');
        $filters = $this->filtersFrom($request);

        $data = $this->overview->compute($scope, $period, $filters);

        return $this->analyticsResponse($request, $period, $data);
    }

    public function trends(AnalyticsTrendsRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');
        $filters = $this->filtersFrom($request);
        $metric = (string) $request->query('metric');

        $data = $this->trends->compute($scope, $period, $filters, $metric);

        return $this->analyticsResponse($request, $period, $data);
    }

    private function filtersFrom($request): array
    {
        return array_filter([
            'business_type_id' => $request->query('business_type_id'),
            'system_id'        => $request->query('system_id'),
            'location_type'    => $request->query('location_type'),
        ], fn ($v) => $v !== null && $v !== '');
    }
}

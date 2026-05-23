<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Services\Analytics\AnalyticsOnboardingBreakdownService;
use App\Services\Analytics\AnalyticsOnboardingFunnelService;
use App\Services\Analytics\Support\AnalyticsPeriod;
use Illuminate\Http\JsonResponse;

class AnalyticsOnboardingController extends Controller
{
    use AnalyticsResponder;

    public function __construct(
        private AnalyticsOnboardingFunnelService $funnelService,
        private AnalyticsOnboardingBreakdownService $breakdownService,
    ) {}

    public function funnel(AnalyticsFilterRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');
        $filters = $this->filters($request);

        return $this->analyticsResponse($request, $period, $this->funnelService->compute($scope, $period, $filters));
    }

    public function breakdown(AnalyticsFilterRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');
        $filters = $this->filters($request);

        return $this->analyticsResponse($request, $period, $this->breakdownService->compute($scope, $period, $filters));
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

<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsCohortsRequest;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Http\Requests\Analytics\AnalyticsForecastRequest;
use App\Services\Analytics\AnalyticsAnomalyService;
use App\Services\Analytics\AnalyticsCohortService;
use App\Services\Analytics\AnalyticsForecastService;
use App\Services\Analytics\AnalyticsSentimentService;
use App\Services\Analytics\Support\AnalyticsPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 4 "Intelligence" analytics — derived/statistical insights.
 * All four endpoints are admin-or-sale only (trainer → 403 via role middleware
 * and a defence-in-depth guard inside each service).
 */
class AnalyticsIntelligenceController extends Controller
{
    use AnalyticsResponder;

    public function __construct(
        private AnalyticsSentimentService $sentiment,
        private AnalyticsAnomalyService $anomalies,
        private AnalyticsCohortService $cohorts,
        private AnalyticsForecastService $forecast,
    ) {}

    public function sentiment(AnalyticsFilterRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');

        return $this->analyticsResponse(
            $request,
            $period,
            $this->sentiment->compute($scope, $period, $this->filtersFrom($request)),
        );
    }

    public function anomalies(AnalyticsFilterRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');

        return $this->analyticsResponse(
            $request,
            $period,
            $this->anomalies->compute($scope, $period, $this->filtersFrom($request)),
        );
    }

    public function cohorts(AnalyticsCohortsRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request, 'month');
        $scope = $request->attributes->get('analytics_scope');

        $cohortBy = (string) ($request->query('cohort_by') ?? 'month');
        $maxElapsed = (int) ($request->query('max_elapsed') ?? 8);

        return $this->analyticsResponse(
            $request,
            $period,
            $this->cohorts->compute($scope, $period, $this->filtersFrom($request), $cohortBy, $maxElapsed),
        );
    }

    public function forecast(AnalyticsForecastRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');

        $metric = (string) ($request->query('metric') ?? 'onboardings_completed');
        $horizon = (int) ($request->query('horizon') ?? config('coms.analytics.forecast_horizon_default', 4));
        $method = $request->query('method');

        return $this->analyticsResponse(
            $request,
            $period,
            $this->forecast->compute($scope, $period, $this->filtersFrom($request), $metric, $horizon, $method),
        );
    }

    private function filtersFrom(Request $request): array
    {
        return array_filter([
            'business_type_id' => $request->query('business_type_id'),
            'system_id'        => $request->query('system_id'),
            'location_type'    => $request->query('location_type'),
        ], fn ($v) => $v !== null && $v !== '');
    }
}

<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Services\Analytics\AnalyticsSatisfactionService;
use App\Services\Analytics\Support\AnalyticsPeriod;
use Illuminate\Http\JsonResponse;

class AnalyticsSatisfactionController extends Controller
{
    use AnalyticsResponder;

    public function __construct(private AnalyticsSatisfactionService $service) {}

    public function index(AnalyticsFilterRequest $request): JsonResponse
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $scope = $request->attributes->get('analytics_scope');
        $filters = array_filter([
            'business_type_id' => $request->query('business_type_id'),
            'system_id'        => $request->query('system_id'),
            'location_type'    => $request->query('location_type'),
        ], fn ($v) => $v !== null && $v !== '');

        return $this->analyticsResponse($request, $period, $this->service->compute($scope, $period, $filters));
    }
}

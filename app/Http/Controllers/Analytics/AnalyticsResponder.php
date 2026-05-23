<?php

namespace App\Http\Controllers\Analytics;

use App\Services\Analytics\Support\AnalyticsPeriod;
use App\Services\Analytics\Support\AnalyticsScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared response helper for analytics controllers. Builds the standard
 * envelope with the spec's meta block (generated_at, period, compare_period,
 * scope).
 */
trait AnalyticsResponder
{
    protected function analyticsResponse(
        Request $request,
        AnalyticsPeriod $period,
        array $data,
        string $message = 'OK',
    ): JsonResponse {
        /** @var AnalyticsScope $scope */
        $scope = $request->attributes->get('analytics_scope');

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'period' => [
                    'from' => $period->from->toDateString(),
                    'to' => $period->to->toDateString(),
                ],
                'compare_period' => $period->compareFrom && $period->compareTo ? [
                    'from' => $period->compareFrom->toDateString(),
                    'to' => $period->compareTo->toDateString(),
                ] : null,
                'scope' => [
                    'role' => $scope->role,
                    'user_id' => $scope->userId,
                    'scoped_trainer_ids' => $scope->isAdmin() && $scope->overrideTrainerId === null
                        ? null
                        : ($scope->scopedTrainerIds() ?? []),
                ],
            ],
        ]);
    }
}

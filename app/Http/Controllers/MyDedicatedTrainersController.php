<?php

namespace App\Http\Controllers;

use App\Services\Sale\SaleTrainerAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyDedicatedTrainersController extends Controller
{
    public function __construct(
        private readonly SaleTrainerAssignmentService $rosterService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $saleUserId = $request->get('auth_user_id');

        $filters = $request->only(['search', 'status', 'sort_by']);

        $trainers = $this->rosterService->getEnrichedRoster($saleUserId, $filters);

        return response()->json([
            'success' => true,
            'data' => $trainers,
        ]);
    }

    public function overview(Request $request, string $trainerId): JsonResponse
    {
        $saleUserId = $request->get('auth_user_id');

        $overview = $this->rosterService->getTrainerOverview($saleUserId, $trainerId);

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }
}

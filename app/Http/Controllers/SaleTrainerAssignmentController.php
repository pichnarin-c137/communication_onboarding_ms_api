<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\ReplaceSaleRosterRequest;
use App\Services\Sale\SaleTrainerAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleTrainerAssignmentController extends Controller
{
    public function __construct(
        private readonly SaleTrainerAssignmentService $rosterService,
    ) {}

    public function show(string $saleUserId): JsonResponse
    {
        $roster = $this->rosterService->getRoster($saleUserId);

        return response()->json([
            'success' => true,
            'data' => [
                'sale_user_id' => $saleUserId,
                'dedicated_trainers' => $roster,
            ],
        ]);
    }

    public function replace(ReplaceSaleRosterRequest $request, string $saleUserId): JsonResponse
    {
        $result = $this->rosterService->replaceRoster(
            saleUserId: $saleUserId,
            trainerUserIds: $request->input('trainer_ids', []),
            assignedByUserId: $request->get('auth_user_id'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Dedicated trainer roster updated.',
            'data' => [
                'sale_user_id' => $saleUserId,
                'dedicated_trainers' => $result['roster'],
                'added' => $result['added'],
                'removed' => $result['removed'],
            ],
        ]);
    }
}

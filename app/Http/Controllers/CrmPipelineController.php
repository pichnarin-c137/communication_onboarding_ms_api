<?php

namespace App\Http\Controllers;

use App\Services\Crm\CrmDealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmPipelineController extends Controller
{
    public function __construct(
        private readonly CrmDealService $dealService
    ) {}

    public function stats(Request $request): JsonResponse
    {
        // A sale sees stats for their own deals; an admin sees the whole pipeline.
        $scopeUserId = $request->get('auth_role') === 'admin'
            ? null
            : $request->get('auth_user_id');

        return response()->json([
            'success' => true,
            'message' => 'Pipeline stats retrieved successfully.',
            'data' => $this->dealService->pipelineStats($scopeUserId),
        ]);
    }
}

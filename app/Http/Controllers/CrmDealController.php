<?php

namespace App\Http\Controllers;

use App\Exceptions\Business\CrmDealNotFoundException;
use App\Exceptions\Business\DealAlreadyClosedException;
use App\Exceptions\Business\InvalidDealStageTransitionException;
use App\Http\Requests\Crm\LostDealRequest;
use App\Http\Requests\Crm\StoreCrmDealRequest;
use App\Http\Requests\Crm\UpdateCrmDealRequest;
use App\Services\Crm\CrmDealService;
use App\Services\Crm\CrmPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmDealController extends Controller
{
    public function __construct(
        private readonly CrmDealService $dealService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));
        $filters = $request->only(['stage', 'contact_id', 'assigned_to']);

        // A sale only sees their own deals; an admin sees the whole pipeline.
        $scopeUserId = $request->get('auth_role') === 'admin'
            ? null
            : $request->get('auth_user_id');

        $result = $this->dealService->list($filters, $perPage, $page, $scopeUserId);

        return response()->json([
            'success' => true,
            'message' => 'CRM deals retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function store(StoreCrmDealRequest $request): JsonResponse
    {
        $deal = $this->dealService->create(
            $request->validated(),
            $request->get('auth_user_id'),
        );

        return response()->json([
            'success' => true,
            'message' => 'CRM deal created successfully.',
            'data' => CrmPresenter::deal($deal),
        ], 201);
    }

    /**
     * @throws CrmDealNotFoundException
     */
    public function show(string $id): JsonResponse
    {
        $deal = $this->dealService->get($id);

        return response()->json([
            'success' => true,
            'message' => 'CRM deal retrieved successfully.',
            'data' => CrmPresenter::deal($deal),
        ]);
    }

    /**
     * @throws CrmDealNotFoundException
     * @throws InvalidDealStageTransitionException
     */
    public function update(UpdateCrmDealRequest $request, string $id): JsonResponse
    {
        $deal = $this->dealService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'CRM deal updated successfully.',
            'data' => CrmPresenter::deal($deal),
        ]);
    }

    /**
     * @throws CrmDealNotFoundException
     * @throws DealAlreadyClosedException
     */
    public function won(Request $request, string $id): JsonResponse
    {
        $result = $this->dealService->markWon($id, $request->get('auth_user_id'));

        $deal = $result['deal'];
        $client = $result['client'];

        return response()->json([
            'success' => true,
            'message' => 'Deal marked as won. Client synced to COMS.',
            'data' => [
                'deal_id' => $deal->id,
                'client_id' => $client->id,
                'client_name' => $client->company_name,
                'won_at' => $deal->won_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * @throws CrmDealNotFoundException
     * @throws DealAlreadyClosedException
     */
    public function lost(LostDealRequest $request, string $id): JsonResponse
    {
        $deal = $this->dealService->markLost($id, $request->validated()['lost_reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Deal marked as lost.',
            'data' => CrmPresenter::deal($deal),
        ]);
    }
}

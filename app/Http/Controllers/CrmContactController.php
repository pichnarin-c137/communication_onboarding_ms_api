<?php

namespace App\Http\Controllers;

use App\Exceptions\Business\CrmContactNotFoundException;
use App\Http\Requests\Crm\StoreCrmContactRequest;
use App\Http\Requests\Crm\UpdateCrmContactRequest;
use App\Services\Crm\CrmContactService;
use App\Services\Crm\CrmPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmContactController extends Controller
{
    public function __construct(
        private readonly CrmContactService $contactService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));
        $filters = $request->only(['search', 'status', 'business_type_id']);

        $result = $this->contactService->list($filters, $perPage, $page);

        return response()->json([
            'success' => true,
            'message' => 'CRM contacts retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function store(StoreCrmContactRequest $request): JsonResponse
    {
        $contact = $this->contactService->create(
            $request->validated(),
            $request->get('auth_user_id'),
        );

        return response()->json([
            'success' => true,
            'message' => 'CRM contact created successfully.',
            'data' => CrmPresenter::contact($contact),
        ], 201);
    }

    /**
     * @throws CrmContactNotFoundException
     */
    public function show(string $id): JsonResponse
    {
        $contact = $this->contactService->get($id);

        return response()->json([
            'success' => true,
            'message' => 'CRM contact retrieved successfully.',
            'data' => CrmPresenter::contact($contact),
        ]);
    }

    /**
     * @throws CrmContactNotFoundException
     */
    public function update(UpdateCrmContactRequest $request, string $id): JsonResponse
    {
        $contact = $this->contactService->get($id);
        $contact = $this->contactService->update($contact, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'CRM contact updated successfully.',
            'data' => CrmPresenter::contact($contact),
        ]);
    }

    /**
     * @throws CrmContactNotFoundException
     */
    public function destroy(string $id): JsonResponse
    {
        $contact = $this->contactService->get($id);
        $this->contactService->delete($contact);

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted.',
            'data' => null,
        ]);
    }

    /**
     * @throws CrmContactNotFoundException
     */
    public function deals(string $id): JsonResponse
    {
        $deals = $this->contactService->listDeals($id);

        return response()->json([
            'success' => true,
            'message' => 'Deals retrieved successfully.',
            'data' => $deals,
        ]);
    }
}

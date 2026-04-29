<?php

namespace App\Http\Controllers;

use App\Exceptions\Business\BusinessTypeNotFoundException;
use App\Http\Requests\Business\StoreBusinessTypeRequest;
use App\Http\Requests\Business\UpdateBusinessTypeRequest;
use App\Models\BusinessType;
use App\Services\Business\BusinessTypeService;
use Illuminate\Http\JsonResponse;

class BusinessTypeController extends Controller
{
    public function __construct(
        private BusinessTypeService $businessTypeService
    ) {}

    public function index(): JsonResponse
    {
        $perPage = max(1, min(100, (int) request()->input('per_page', 15)));
        $page = max(1, (int) request()->input('page', 1));
        $search = trim((string) request()->input('search', ''));

        $result = $this->businessTypeService->list($perPage, $page, $search);

        return response()->json([
            'success' => true,
            'message' => 'Business types retrieved successfully.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function store(StoreBusinessTypeRequest $request): JsonResponse
    {
        $businessType = $this->businessTypeService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Business type created successfully.',
            'data' => $businessType,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $businessType = $this->businessTypeService->get($id);

        return response()->json([
            'success' => true,
            'message' => 'Business type retrieved successfully.',
            'data' => $businessType,
        ]);
    }

    public function update(UpdateBusinessTypeRequest $request, string $id): JsonResponse
    {
        $businessType = $this->resolveBusinessType($id);
        $businessType = $this->businessTypeService->update($businessType, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Business type updated successfully.',
            'data' => $businessType,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $businessType = $this->resolveBusinessType($id);
        $this->businessTypeService->delete($businessType);

        return response()->json([
            'success' => true,
            'message' => 'Business type deleted successfully.',
            'data' => null,
        ]);
    }

    private function resolveBusinessType(string $id): BusinessType
    {
        $businessType = BusinessType::find($id);

        if (! $businessType) {
            throw new BusinessTypeNotFoundException(
                "Business type with ID '{$id}' not found.",
                context: ['business_type_id' => $id]
            );
        }

        return $businessType;
    }
}

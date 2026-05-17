<?php

namespace App\Http\Controllers;

use App\Services\Logging\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['user_id', 'action', 'from', 'to']);
        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = max((int) $request->input('page', 1), 1);

        $result = $this->activityLogger->getAll($filters, $perPage, $page);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function forUser(Request $request, string $userId): JsonResponse
    {
        $filters = $request->only(['action', 'from', 'to']);
        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = max((int) $request->input('page', 1), 1);

        $result = $this->activityLogger->getAll(
            array_merge($filters, ['user_id' => $userId]),
            $perPage,
            $page
        );

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }
}

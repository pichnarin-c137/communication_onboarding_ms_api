<?php

namespace App\Http\Controllers;

use App\Services\SystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function __construct(
        private SystemService $systemService
    ) {}

    public function listSystems(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'limit']);

        $systems = $this->systemService->listSystems($filters);

        return response()->json([
            'success' => true,
            'data' => $systems,
        ]);
    }
}

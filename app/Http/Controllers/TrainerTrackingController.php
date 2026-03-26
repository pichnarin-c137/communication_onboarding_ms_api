<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationPingRequest;
use App\Http\Requests\TrainerStatusChangeRequest;
use App\Services\Tracking\TrainerStatusService;
use App\Services\Tracking\TrainerTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class TrainerTrackingController extends Controller
{
    public function __construct(
        private TrainerTrackingService $trackingService,
        private TrainerStatusService $statusService,
    ) {}

    public function ping(LocationPingRequest $request): JsonResponse
    {
        $trainerId = $request->get('auth_user_id');
        $this->trackingService->processPing($trainerId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ping received.',
        ]);
    }

    public function changeStatus(TrainerStatusChangeRequest $request): JsonResponse
    {
        $trainerId = $request->get('auth_user_id');
        $log = $this->statusService->changeStatus(
            $trainerId,
            $request->input('status'),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Status updated.',
            'data' => $log,
        ]);
    }

    /**
     * Lightweight GPS check-in — seeds the trainer's position in Redis
     * without trail logging, anomaly checks, or broadcasting.
     * Called once at login so sales always have a baseline position.
     */
    public function checkin(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $trainerId = $request->get('auth_user_id');
        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');

        // Seed position in Redis GEO (same key used by live tracking)
        Redis::geoadd(TrainerTrackingService::KEY_LOCATIONS, $lng, $lat, $trainerId);

        // Update status cache with coordinates (preserve existing status)
        $statusJson = Redis::get(TrainerTrackingService::statusKey($trainerId));
        $status = $statusJson ? json_decode($statusJson, true) : [];

        $status['lat'] = $lat;
        $status['lng'] = $lng;
        $status['status'] = $status['status'] ?? 'at_office';
        $status['updated_at'] = now()->toISOString();

        $ttl = config('coms.tracking.status_cache_ttl', 86400);
        Redis::setex(TrainerTrackingService::statusKey($trainerId), $ttl, json_encode($status));

        return response()->json([
            'success' => true,
            'message' => 'Check-in received.',
        ]);
    }
}

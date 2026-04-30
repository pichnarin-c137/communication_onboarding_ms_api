<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TrainerActivityLog;
use App\Models\TrainerLocationPing;
use App\Services\Tracking\EtaService;
use App\Services\Tracking\TrainerTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainerLiveStatusController extends Controller
{
    public function __construct(
        private readonly TrainerTrackingService $trackingService,
        private readonly EtaService $etaService,
    ) {}

    public function liveStatus(): JsonResponse
    {
        $positions = $this->trackingService->getAllTrainerPositions();

        // Enrich with ETA data where available
        foreach ($positions as &$pos) {
            $eta = $this->etaService->getCachedEta($pos['id']);
            $pos['eta'] = $eta;
        }

        return response()->json([
            'success' => true,
            'data' => $positions,
        ]);
    }

    public function todayActivity(string $id): JsonResponse
    {
        $logs = TrainerActivityLog::forTrainerToday($id)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'status' => $log->status,
                'customer_id' => $log->customer_id,
                'appointment_id' => $log->appointment_id,
                'detection_method' => $log->detection_method,
                'accuracy' => $log->accuracy,
                'speed' => $log->speed,
                'created_at' => $log->created_at,
            ]);

        $trail = $this->trackingService->getTrail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $logs,
                'trail' => $trail,
            ],
        ]);
    }

    public function activityLog(Request $request, string $id): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());

        $logs = TrainerActivityLog::forTrainerOnDate($id, $date)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'status' => $log->status,
                'customer_id' => $log->customer_id,
                'appointment_id' => $log->appointment_id,
                'detection_method' => $log->detection_method,
                'accuracy' => $log->accuracy,
                'speed' => $log->speed,
                'created_at' => $log->created_at,
            ]);

        $pings = TrainerLocationPing::where('trainer_id', $id)
            ->whereDate('pinged_at', $date)
            ->orderBy('pinged_at')
            ->get()
            ->map(fn ($ping) => [
                'lat' => $ping->latitude,
                'lng' => $ping->longitude,
                'accuracy' => $ping->accuracy,
                'speed' => $ping->speed,
                'pinged_at' => $ping->pinged_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'events' => $logs,
                'route' => $pings,
            ],
        ]);
    }

    public function customerLocations(): JsonResponse
    {
        $clients = Client::whereNotNull('headquarter_latitude')
            ->whereNotNull('headquarter_longitude')
            ->get(['id', 'company_code', 'company_name', 'headquarter_latitude', 'headquarter_longitude', 'geofence_radius']);

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->get('auth_user_id');
        $limit = (int) $request->query('limit', 20);

        $notifications = $this->notificationService->getRecent($userId, $limit);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = $request->get('auth_user_id');
        $count = $this->notificationService->getUnreadCount($userId);

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $count],
        ]);
    }

    public function markRead(string $id): JsonResponse
    {
        $updated = $this->notificationService->markAsRead($id);

        return response()->json([
            'success' => true,
            'message' => $updated ? 'Notification marked as read' : 'Notification not found',
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $userId = $request->get('auth_user_id');
        $count = $this->notificationService->markAllAsRead($userId);

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications marked as read",
            'data' => ['updated_count' => $count],
        ]);
    }
}

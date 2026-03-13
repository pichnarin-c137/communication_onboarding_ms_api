<?php

namespace App\Services\Notification;

use App\Models\Notification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notify(array $userIds, string $type, string $title, string $message, ?array $relatedEntity = null): void
    {
        if (empty($userIds)) {
            return;
        }

        $now = now();
        $rows = array_map(function ($userId) use ($type, $title, $message, $relatedEntity, $now) {
            return [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $userId,
                'client_contact_id' => null,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'related_entity_type' => $relatedEntity['type'] ?? null,
                'related_entity_id' => $relatedEntity['id'] ?? null,
                'is_read' => false,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $userIds);

        Notification::insert($rows);
    }

    public function notifyContact(array $contactIds, string $type, string $title, string $message): void
    {
        if (empty($contactIds)) {
            return;
        }

        $now = now();
        $rows = array_map(function ($contactId) use ($type, $title, $message, $now) {
            return [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => null,
                'client_contact_id' => $contactId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'related_entity_type' => null,
                'related_entity_id' => null,
                'is_read' => false,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $contactIds);

        Notification::insert($rows);
    }

    public function markAsRead(string $notificationId): bool
    {
        return (bool) Notification::where('id', $notificationId)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    public function markAllAsRead(string $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    public function getUnreadCount(string $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function getRecent(string $userId, int $limit = 20): Collection
    {
        return Notification::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}

<?php

namespace App\Services\Logging;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActivityLogger
{
    const LOGIN = 'login';

    const LOGOUT = 'logout';

    const LOGIN_FAILED = 'login_failed';

    const REQUEST_CREATED = 'request_created';

    const REQUEST_CANCELLED = 'request_cancelled';

    const TRAINER_ASSIGNED = 'trainer_assigned';

    const ASSIGNMENT_ACCEPTED = 'assignment_accepted';

    const ASSIGNMENT_REJECTED = 'assignment_rejected';

    const SESSION_CREATED = 'session_created';

    const SESSION_STARTED = 'session_started';

    const SESSION_COMPLETED = 'session_completed';

    const SESSION_CANCELLED = 'session_cancelled';

    const SESSION_RESCHEDULED = 'session_rescheduled';

    const STAGE_SKIPPED = 'stage_skipped';

    const ATTENDANCE_MARKED = 'attendance_marked';

    const STAGE_CREATED = 'stage_created';

    const STAGE_UPDATED = 'stage_updated';

    const PASSWORD_CHANGED = 'password_changed';

    const PASSWORD_RESET = 'password_reset';

    const FORGOT_PASSWORD = 'forgot_password';

    const USER_CREATED = 'user_created';

    const USER_SOFT_DELETED = 'user_soft_deleted';

    const USER_HARD_DELETED = 'user_hard_deleted';

    const USER_RESTORED = 'user_restored';

    const USER_SUSPENDED = 'user_suspended';

    const USER_UNSUSPENDED = 'user_unsuspended';

    const USER_UPDATED = 'user_updated';

    const APPOINTMENT_CREATED = 'appointment_created';

    const APPOINTMENT_UPDATED = 'appointment_updated';

    const APPOINTMENT_LEAVE_OFFICE = 'appointment_leave_office';

    const APPOINTMENT_STARTED = 'appointment_started';

    const APPOINTMENT_COMPLETED = 'appointment_completed';

    const APPOINTMENT_CANCELLED = 'appointment_cancelled';

    const APPOINTMENT_RESCHEDULED = 'appointment_rescheduled';

    const ONBOARDING_STARTED = 'onboarding_started';

    const ONBOARDING_COMPLETED = 'onboarding_completed';

    const ONBOARDING_CANCELLED = 'onboarding_cancelled';

    const ONBOARDING_HELD = 'onboarding_held';

    const ONBOARDING_RESUMED = 'onboarding_resumed';

    const ONBOARDING_REVISION_REQUESTED = 'onboarding_revision_requested';

    const ONBOARDING_REVISION_ACKNOWLEDGED = 'onboarding_revision_acknowledged';

    const ONBOARDING_REOPENED = 'onboarding_reopened';

    const ONBOARDING_TRAINER_REASSIGNED = 'onboarding_trainer_reassigned';

    const LESSON_SENT = 'lesson_sent';

    const TRAINER_STATUS_CHANGED = 'trainer_status_changed';

    const PLAYLIST_CREATED = 'playlist_created';

    const PLAYLIST_UPDATED = 'playlist_updated';

    const PLAYLIST_DELETED = 'playlist_deleted';

    const PLAYLIST_VIDEO_ADDED = 'playlist_video_added';

    const PLAYLIST_VIDEO_UPDATED = 'playlist_video_updated';

    const PLAYLIST_VIDEO_DELETED = 'playlist_video_deleted';

    const BUSINESS_TYPE_CREATED = 'business_type_created';

    const BUSINESS_TYPE_UPDATED = 'business_type_updated';

    const BUSINESS_TYPE_DELETED = 'business_type_deleted';

    const COMPANY_CREATED = 'company_created';

    const COMPANY_UPDATED = 'company_updated';

    const COMPANY_DELETED = 'company_deleted';

    public function __construct(
        private Request $request
    ) {}

    public function log(string $action, string $description = '', array $metadata = [], ?string $userId = null): UserActivityLog
    {
        $resolvedUserId = $userId ?? $this->request->get('auth_user_id');

        return UserActivityLog::create([
            'user_id' => $resolvedUserId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }

    public function getForUser(string $userId, array $filters = []): Collection
    {
        $query = UserActivityLog::where('user_id', $userId)
            ->orderByDesc('created_at');

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (isset($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        return $query->get();
    }

    public function getAll(array $filters = [], int $perPage = 20, int $page = 1): array
    {
        $query = UserActivityLog::with('user:id,first_name,last_name')
            ->orderByDesc('created_at');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ];
    }
}

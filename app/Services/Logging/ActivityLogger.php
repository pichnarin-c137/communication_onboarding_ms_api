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

        if (isset($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        return $query->get();
    }
}

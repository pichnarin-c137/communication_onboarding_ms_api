<?php

namespace App\Services\Sale;

use App\Exceptions\Business\InvalidUserRoleForRosterException;
use App\Exceptions\Business\SuspendedOrDeletedTrainerCannotBeAssignedException;
use App\Exceptions\Business\TrainerHasActiveCommitmentsException;
use App\Exceptions\Business\TrainerNotInSaleRosterException;
use App\Exceptions\Business\TrainerWorkloadExceededException;
use App\Exceptions\UserNotFoundException;
use App\Models\Appointment;
use App\Models\Credential;
use App\Models\OnboardingRequest;
use App\Models\SaleTrainerAssignment;
use App\Models\User;
use App\Services\Logging\ActivityLogger;
use App\Services\Tracking\TrainerStatusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SaleTrainerAssignmentService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly TrainerStatusService $trainerStatusService,
    ) {}

    public function listCacheKey(string $saleUserId): string
    {
        return "sale_roster:enriched:$saleUserId";
    }

    public function invalidateEnrichedCache(string $saleUserId): void
    {
        Cache::forget($this->listCacheKey($saleUserId));
    }

    /**
     * Invalidate every sale's cached roster that contains the given trainer.
     * Called from appointment / onboarding write paths where status counters change.
     */
    public function invalidateCachesContainingTrainer(string $trainerUserId): void
    {
        $saleIds = SaleTrainerAssignment::query()
            ->where('trainer_user_id', $trainerUserId)
            ->distinct('sale_user_id')
            ->pluck('sale_user_id');

        foreach ($saleIds as $saleId) {
            $this->invalidateEnrichedCache($saleId);
        }
    }

    /**
     * Return the active roster for a sale user as an array of trainer summaries.
     *
     * @return array<int, array{trainer_user_id: string, first_name: ?string, last_name: ?string, full_name: string, assigned_at: ?string, assigned_by_id: ?string}>
     */
    public function getRoster(string $saleUserId): array
    {
        $this->assertUserHasRole($saleUserId, 'sale');

        return SaleTrainerAssignment::query()
            ->where('sale_user_id', $saleUserId)
            ->with(['trainerUser:id,first_name,last_name,is_suspended,deleted_at'])
            ->orderBy('assigned_at')
            ->get()
            ->map(fn (SaleTrainerAssignment $row) => [
                'trainer_user_id' => $row->trainer_user_id,
                'first_name' => $row->trainerUser?->first_name,
                'last_name' => $row->trainerUser?->last_name,
                'full_name' => $row->trainerUser
                    ? trim("{$row->trainerUser->first_name} {$row->trainerUser->last_name}")
                    : '',
                'assigned_at' => $row->assigned_at?->toIso8601String(),
                'assigned_by_id' => $row->assigned_by_id,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Atomically replace a sale user's roster with the given trainer IDs.
     *
     * @param  array<int, string>  $trainerUserIds
     * @return array{roster: array, added: array<int, string>, removed: array<int, string>}
     */
    public function replaceRoster(string $saleUserId, array $trainerUserIds, ?string $assignedByUserId): array
    {
        $this->assertUserHasRole($saleUserId, 'sale');

        $desired = array_values(array_unique($trainerUserIds));

        $currentRows = SaleTrainerAssignment::query()
            ->where('sale_user_id', $saleUserId)
            ->get();

        $currentIds = $currentRows->pluck('trainer_user_id')->all();

        $toAdd = array_values(array_diff($desired, $currentIds));
        $toRemove = array_values(array_diff($currentIds, $desired));

        foreach ($toAdd as $trainerId) {
            $this->assertTrainerIsAssignable($trainerId);
            $this->assertTrainerEligibleForAssignment($trainerId, $saleUserId);
        }

        DB::transaction(function () use ($saleUserId, $toAdd, $toRemove, $assignedByUserId, $currentRows) {
            if (! empty($toRemove)) {
                SaleTrainerAssignment::query()
                    ->where('sale_user_id', $saleUserId)
                    ->whereIn('trainer_user_id', $toRemove)
                    ->delete();
            }

            foreach ($toAdd as $trainerId) {
                $existing = SaleTrainerAssignment::withTrashed()
                    ->where('sale_user_id', $saleUserId)
                    ->where('trainer_user_id', $trainerId)
                    ->first();

                if ($existing && $existing->trashed()) {
                    $existing->restore();
                    $existing->update([
                        'assigned_by_id' => $assignedByUserId,
                        'assigned_at' => Carbon::now(),
                    ]);

                    continue;
                }

                if ($existing) {
                    continue;
                }

                SaleTrainerAssignment::create([
                    'sale_user_id' => $saleUserId,
                    'trainer_user_id' => $trainerId,
                    'assigned_by_id' => $assignedByUserId,
                    'assigned_at' => Carbon::now(),
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityLogger::SALE_ROSTER_REPLACED,
            'Sale dedicated trainer roster replaced',
            [
                'sale_user_id' => $saleUserId,
                'added' => $toAdd,
                'removed' => $toRemove,
                'final_size' => count($desired),
            ],
            $assignedByUserId,
        );

        $this->invalidateEnrichedCache($saleUserId);

        return [
            'roster' => $this->getRoster($saleUserId),
            'added' => $toAdd,
            'removed' => $toRemove,
        ];
    }

    /**
     * Throws TrainerNotInSaleRosterException unless the trainer is on the sale's active roster.
     */
    public function assertTrainerInRoster(string $saleUserId, string $trainerUserId): void
    {
        $exists = SaleTrainerAssignment::query()
            ->where('sale_user_id', $saleUserId)
            ->where('trainer_user_id', $trainerUserId)
            ->exists();

        if (! $exists) {
            throw new TrainerNotInSaleRosterException(
                context: [
                    'sale_user_id' => $saleUserId,
                    'trainer_user_id' => $trainerUserId,
                ],
            );
        }
    }

    /**
     * Throws TrainerHasActiveCommitmentsException if trainer cannot be suspended/soft-deleted yet.
     */
    public function assertCanSuspendOrSoftDeleteTrainer(string $trainerUserId): void
    {
        $activeOnboardings = OnboardingRequest::query()
            ->where('trainer_id', $trainerUserId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $inProgressAppointments = Appointment::query()
            ->where('trainer_id', $trainerUserId)
            ->where('status', 'in_progress')
            ->count();

        $rosterRows = SaleTrainerAssignment::query()
            ->where('trainer_user_id', $trainerUserId)
            ->count();

        if ($activeOnboardings > 0 || $inProgressAppointments > 0 || $rosterRows > 0) {
            throw new TrainerHasActiveCommitmentsException(
                context: [
                    'trainer_user_id' => $trainerUserId,
                    'active_onboardings' => $activeOnboardings,
                    'in_progress_appointments' => $inProgressAppointments,
                    'roster_memberships' => $rosterRows,
                ],
            );
        }
    }

    /**
     * Remove all of a sale user's roster rows. Called during sale soft-delete cascade.
     */
    public function detachAllForSale(string $saleUserId): void
    {
        SaleTrainerAssignment::query()
            ->where('sale_user_id', $saleUserId)
            ->delete();

        $this->invalidateEnrichedCache($saleUserId);
    }

    /**
     * Sale-facing enriched roster: profile + live status + workload + last interaction.
     * Cached at coms.sale_roster.enriched_list_ttl seconds, keyed by sale user id.
     * Live status is always read fresh from Redis after the cache hit so the dot
     * does not go stale.
     *
     * @param  array{search?: string, status?: string, sort_by?: string}  $filters
     */
    public function getEnrichedRoster(string $saleUserId, array $filters = []): array
    {
        $this->assertUserHasRole($saleUserId, 'sale');

        $ttl = (int) config('coms.sale_roster.enriched_list_ttl', 30);
        $cacheKey = $this->listCacheKey($saleUserId);

        $base = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($saleUserId) {
            return $this->buildEnrichedRosterBase($saleUserId);
        });

        $hydrated = array_map(function (array $row) {
            $statusData = $this->trainerStatusService->getStatusData($row['trainer_user_id']);
            $row['live_status'] = $statusData['status'];
            $row['live_status_updated_at'] = $statusData['status_updated_at'];

            return $row;
        }, $base);

        if (! empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $hydrated = array_values(array_filter($hydrated, function (array $row) use ($search) {
                return str_contains(mb_strtolower($row['full_name']), $search)
                    || str_contains(mb_strtolower($row['phone_number'] ?? ''), $search);
            }));
        }

        if (! empty($filters['status'])) {
            $hydrated = array_values(array_filter(
                $hydrated,
                fn (array $row) => $row['live_status'] === $filters['status'],
            ));
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        usort($hydrated, function (array $a, array $b) use ($sortBy) {
            return match ($sortBy) {
                'current_workload' => ($b['workload']['pending_appointments'] + $b['workload']['active_onboardings'])
                    <=> ($a['workload']['pending_appointments'] + $a['workload']['active_onboardings']),
                'last_interaction' => strcmp(
                    (string) ($b['last_interaction_at'] ?? ''),
                    (string) ($a['last_interaction_at'] ?? ''),
                ),
                default => strcmp(
                    mb_strtolower($a['full_name']),
                    mb_strtolower($b['full_name']),
                ),
            };
        });

        return $hydrated;
    }

    /**
     * Detailed snapshot for one roster trainer — drives the FE trainer detail page.
     * Always fresh (not cached). Throws 403 if trainer is not in the sale's roster.
     */
    public function getTrainerOverview(string $saleUserId, string $trainerUserId): array
    {
        $this->assertUserHasRole($saleUserId, 'sale');
        $this->assertTrainerInRoster($saleUserId, $trainerUserId);

        $trainer = User::with(['credential:user_id,email,phone_number'])
            ->find($trainerUserId, ['id', 'first_name', 'last_name', 'nationality']);

        $rosterRow = SaleTrainerAssignment::query()
            ->where('sale_user_id', $saleUserId)
            ->where('trainer_user_id', $trainerUserId)
            ->first();

        $config = config('coms.sale_roster');

        $activeOnboardings = OnboardingRequest::query()
            ->where('trainer_id', $trainerUserId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $pendingAppointments = Appointment::query()
            ->where('trainer_id', $trainerUserId)
            ->where('status', 'pending')
            ->count();

        $inProgressAppointment = Appointment::query()
            ->where('trainer_id', $trainerUserId)
            ->where('status', 'in_progress')
            ->exists();

        $today = Carbon::today();
        $todayCount = Appointment::query()
            ->where('trainer_id', $trainerUserId)
            ->whereDate('scheduled_date', $today)
            ->count();

        $nextAppointment = Appointment::query()
            ->with(['client:id,company_name'])
            ->where('trainer_id', $trainerUserId)
            ->whereDate('scheduled_date', $today)
            ->whereIn('status', ['pending', 'leave_office'])
            ->orderBy('scheduled_start_time')
            ->first(['id', 'scheduled_start_time', 'scheduled_end_time', 'client_id', 'location_type', 'status']);

        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $apptCounts = Appointment::query()
            ->where('trainer_id', $trainerUserId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $obCounts = OnboardingRequest::query()
            ->where('trainer_id', $trainerUserId)
            ->where('appointment_id', '!=', null) // tied to a sale's appointment
            ->whereHas('appointment', fn ($q) => $q->where('creator_id', $saleUserId))
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $statusData = $this->trainerStatusService->getStatusData($trainerUserId);

        return [
            'trainer' => [
                'trainer_user_id' => $trainer?->id,
                'first_name' => $trainer?->first_name,
                'last_name' => $trainer?->last_name,
                'full_name' => $trainer ? trim("{$trainer->first_name} {$trainer->last_name}") : '',
                'email' => $trainer?->credential?->email,
                'phone_number' => $trainer?->credential?->phone_number,
                'avatar_url' => null,
                'nationality' => $trainer?->nationality,
                'assigned_at' => $rosterRow?->assigned_at?->toIso8601String(),
                'assigned_by_id' => $rosterRow?->assigned_by_id,
            ],
            'live' => [
                'status' => $statusData['status'],
                'current_appointment_id' => $statusData['current_appointment_id'],
                'status_updated_at' => $statusData['status_updated_at'],
            ],
            'workload' => [
                'active_onboardings' => $activeOnboardings,
                'pending_appointments' => $pendingAppointments,
                'in_progress_appointment' => $inProgressAppointment,
                'max_sales_per_trainer' => (int) $config['max_sales_per_trainer'],
                'max_pending_appointments_per_trainer' => (int) $config['max_pending_appointments_per_trainer'],
                'max_concurrent_active_onboardings_per_trainer' => (int) $config['max_concurrent_active_onboardings_per_trainer'],
            ],
            'today' => [
                'appointments_count' => $todayCount,
                'next_appointment' => $nextAppointment ? [
                    'id' => $nextAppointment->id,
                    'scheduled_start_time' => $nextAppointment->scheduled_start_time,
                    'scheduled_end_time' => $nextAppointment->scheduled_end_time,
                    'client_name' => $nextAppointment->client?->company_name,
                    'location_type' => $nextAppointment->location_type,
                    'status' => $nextAppointment->status,
                ] : null,
            ],
            'summary_30d' => [
                'completed_appointments' => (int) ($apptCounts['done'] ?? 0),
                'cancelled_appointments' => (int) ($apptCounts['cancelled'] ?? 0),
                'rescheduled_appointments' => (int) ($apptCounts['rescheduled'] ?? 0),
                'no_show_appointments' => 0,
                'active_onboardings_for_this_sale' => (int) (($obCounts['pending'] ?? 0) + ($obCounts['in_progress'] ?? 0)),
                'completed_onboardings_for_this_sale' => (int) ($obCounts['completed'] ?? 0),
            ],
        ];
    }

    /**
     * Build the cacheable base of getEnrichedRoster — DB-heavy fields only.
     * Live status is layered in afterwards from Redis.
     */
    private function buildEnrichedRosterBase(string $saleUserId): array
    {
        $rosterRows = SaleTrainerAssignment::query()
            ->where('sale_user_id', $saleUserId)
            ->with([
                'trainerUser:id,first_name,last_name,is_suspended',
                'trainerUser.credential:user_id,email,phone_number',
            ])
            ->get();

        if ($rosterRows->isEmpty()) {
            return [];
        }

        $trainerIds = $rosterRows->pluck('trainer_user_id')->all();

        $onboardingCounts = OnboardingRequest::query()
            ->whereIn('trainer_id', $trainerIds)
            ->whereIn('status', ['pending', 'in_progress'])
            ->selectRaw('trainer_id, count(*) as c')
            ->groupBy('trainer_id')
            ->pluck('c', 'trainer_id')
            ->toArray();

        $pendingApptCounts = Appointment::query()
            ->whereIn('trainer_id', $trainerIds)
            ->where('status', 'pending')
            ->selectRaw('trainer_id, count(*) as c')
            ->groupBy('trainer_id')
            ->pluck('c', 'trainer_id')
            ->toArray();

        $inProgressApptTrainers = Appointment::query()
            ->whereIn('trainer_id', $trainerIds)
            ->where('status', 'in_progress')
            ->pluck('trainer_id')
            ->unique()
            ->flip()
            ->toArray();

        $today = Carbon::today();
        $todayApptCounts = Appointment::query()
            ->whereIn('trainer_id', $trainerIds)
            ->whereDate('scheduled_date', $today)
            ->selectRaw('trainer_id, count(*) as c')
            ->groupBy('trainer_id')
            ->pluck('c', 'trainer_id')
            ->toArray();

        $lastInteractions = Appointment::query()
            ->where('creator_id', $saleUserId)
            ->whereIn('trainer_id', $trainerIds)
            ->selectRaw('trainer_id, max(created_at) as last_at')
            ->groupBy('trainer_id')
            ->pluck('last_at', 'trainer_id')
            ->toArray();

        return $rosterRows->map(function (SaleTrainerAssignment $row) use (
            $onboardingCounts,
            $pendingApptCounts,
            $inProgressApptTrainers,
            $todayApptCounts,
            $lastInteractions,
        ) {
            $tid = $row->trainer_user_id;
            $trainer = $row->trainerUser;

            return [
                'trainer_user_id' => $tid,
                'first_name' => $trainer?->first_name,
                'last_name' => $trainer?->last_name,
                'full_name' => $trainer ? trim("{$trainer->first_name} {$trainer->last_name}") : '',
                'email' => $trainer?->credential?->email,
                'phone_number' => $trainer?->credential?->phone_number,
                'avatar_url' => null,
                'assigned_at' => $row->assigned_at?->toIso8601String(),
                'workload' => [
                    'active_onboardings' => (int) ($onboardingCounts[$tid] ?? 0),
                    'pending_appointments' => (int) ($pendingApptCounts[$tid] ?? 0),
                    'in_progress_appointment' => isset($inProgressApptTrainers[$tid]),
                    'today_appointments' => (int) ($todayApptCounts[$tid] ?? 0),
                ],
                'last_interaction_at' => isset($lastInteractions[$tid])
                    ? Carbon::parse($lastInteractions[$tid])->toIso8601String()
                    : null,
            ];
        })->values()->toArray();
    }

    /**
     * Whether a given user has an active roster (any rows). Used by UserService guards.
     */
    public function trainerHasAnyRoster(string $trainerUserId): bool
    {
        return SaleTrainerAssignment::query()
            ->where('trainer_user_id', $trainerUserId)
            ->exists();
    }

    /**
     * Validate that the candidate user can hold a roster slot: must exist, role=trainer,
     * not suspended, not soft-deleted.
     */
    private function assertTrainerIsAssignable(string $trainerUserId): void
    {
        $user = User::withTrashed()->with('role:id,role')->find($trainerUserId);

        if (! $user) {
            throw new UserNotFoundException('Trainer not found', 0, null, ['trainer_user_id' => $trainerUserId]);
        }

        if (! $user->role || $user->role->role !== 'trainer') {
            throw new InvalidUserRoleForRosterException(
                context: ['trainer_user_id' => $trainerUserId, 'actual_role' => $user->role?->role],
            );
        }

        if ($user->trashed() || $user->is_suspended) {
            throw new SuspendedOrDeletedTrainerCannotBeAssignedException(
                context: [
                    'trainer_user_id' => $trainerUserId,
                    'is_suspended' => (bool) $user->is_suspended,
                    'is_deleted' => $user->trashed(),
                ],
            );
        }
    }

    /**
     * Apply the four configurable workload caps against the trainer.
     * Caps that the trainer is about to land on the requested sale are evaluated
     * EXCLUDING the requested sale itself, so re-PUTs of an unchanged set don't fail.
     */
    private function assertTrainerEligibleForAssignment(string $trainerUserId, string $saleUserId): void
    {
        $config = config('coms.sale_roster');

        $activeOnboardings = OnboardingRequest::query()
            ->where('trainer_id', $trainerUserId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        if ($activeOnboardings >= (int) $config['max_concurrent_active_onboardings_per_trainer']) {
            throw new TrainerWorkloadExceededException(
                context: [
                    'cap' => 'max_concurrent_active_onboardings_per_trainer',
                    'current' => $activeOnboardings,
                    'threshold' => (int) $config['max_concurrent_active_onboardings_per_trainer'],
                    'trainer_user_id' => $trainerUserId,
                ],
            );
        }

        $salesServed = SaleTrainerAssignment::query()
            ->where('trainer_user_id', $trainerUserId)
            ->where('sale_user_id', '!=', $saleUserId)
            ->distinct('sale_user_id')
            ->count('sale_user_id');

        if ($salesServed >= (int) $config['max_sales_per_trainer']) {
            throw new TrainerWorkloadExceededException(
                context: [
                    'cap' => 'max_sales_per_trainer',
                    'current' => $salesServed,
                    'threshold' => (int) $config['max_sales_per_trainer'],
                    'trainer_user_id' => $trainerUserId,
                ],
            );
        }

        if ($config['block_if_in_progress_appointment']) {
            $inProgress = Appointment::query()
                ->where('trainer_id', $trainerUserId)
                ->where('status', 'in_progress')
                ->exists();

            if ($inProgress) {
                throw new TrainerWorkloadExceededException(
                    context: [
                        'cap' => 'block_if_in_progress_appointment',
                        'current' => 1,
                        'threshold' => 0,
                        'trainer_user_id' => $trainerUserId,
                    ],
                );
            }
        }

        $pendingAppointments = Appointment::query()
            ->where('trainer_id', $trainerUserId)
            ->where('status', 'pending')
            ->count();

        if ($pendingAppointments >= (int) $config['max_pending_appointments_per_trainer']) {
            throw new TrainerWorkloadExceededException(
                context: [
                    'cap' => 'max_pending_appointments_per_trainer',
                    'current' => $pendingAppointments,
                    'threshold' => (int) $config['max_pending_appointments_per_trainer'],
                    'trainer_user_id' => $trainerUserId,
                ],
            );
        }
    }

    private function assertUserHasRole(string $userId, string $expectedRole): void
    {
        $user = User::with('role:id,role')->find($userId);

        if (! $user) {
            throw new UserNotFoundException('User not found', 0, null, ['user_id' => $userId]);
        }

        if (! $user->role || $user->role->role !== $expectedRole) {
            throw new InvalidUserRoleForRosterException(
                context: [
                    'user_id' => $userId,
                    'expected_role' => $expectedRole,
                    'actual_role' => $user->role?->role,
                ],
            );
        }
    }
}

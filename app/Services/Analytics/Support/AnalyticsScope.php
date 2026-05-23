<?php

namespace App\Services\Analytics\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Immutable value object describing what data the caller is allowed to see.
 * Built by AnalyticsScopeResolver in the ResolveAnalyticsScope middleware.
 *
 * Role rules:
 *   admin   → unrestricted (trainerIds is the literal []; null sentinel for "no filter")
 *   sale    → roster trainers + own-created appointments
 *   trainer → only own rows (trainerIds = [self])
 *
 * Admin overrides via trainer_id / sale_id query params are honoured.
 * Sale/trainer overrides have already been rejected by the resolver
 * if they fall outside scope.
 */
final class AnalyticsScope
{
    /**
     * @param  list<string>  $trainerIds  Trainer UUIDs the caller can see
     *                                    (empty list = unrestricted; never present for trainers/sales)
     */
    public function __construct(
        public readonly string $role,
        public readonly string $userId,
        public readonly array $trainerIds = [],
        public readonly ?string $overrideTrainerId = null,
        public readonly ?string $overrideSaleId = null,
    ) {}

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSale(): bool
    {
        return $this->role === 'sale';
    }

    public function isTrainer(): bool
    {
        return $this->role === 'trainer';
    }

    /**
     * The trainer UUIDs the response should be limited to.
     * Returns null when there is no trainer-side restriction (admin without override).
     *
     * @return list<string>|null
     */
    public function scopedTrainerIds(): ?array
    {
        if ($this->overrideTrainerId !== null) {
            return [$this->overrideTrainerId];
        }

        if ($this->isAdmin()) {
            return null;
        }

        return $this->trainerIds;
    }

    /**
     * Apply scope to a query against `appointments`.
     * Admin: optionally trainer_id / sale_id (creator_id) overrides.
     * Sale:  (trainer_id IN roster) OR (creator_id = self)
     * Trainer: trainer_id = self
     */
    public function applyAppointmentScope(EloquentBuilder|QueryBuilder $q, string $table = 'appointments'): EloquentBuilder|QueryBuilder
    {
        if ($this->isAdmin()) {
            if ($this->overrideTrainerId !== null) {
                $q->where("{$table}.trainer_id", $this->overrideTrainerId);
            }
            if ($this->overrideSaleId !== null) {
                $q->where("{$table}.creator_id", $this->overrideSaleId);
            }

            return $q;
        }

        if ($this->isSale()) {
            $roster = $this->trainerIds;

            return $q->where(function ($inner) use ($table, $roster) {
                $inner->where("{$table}.creator_id", $this->userId);
                if (! empty($roster)) {
                    $inner->orWhereIn("{$table}.trainer_id", $roster);
                }
            });
        }

        // trainer
        return $q->where("{$table}.trainer_id", $this->userId);
    }

    /**
     * Apply scope to a query against `onboarding_requests`.
     * Same rules as appointments but joined via the parent appointment's creator
     * for sales (since onboarding_requests has no created_by column).
     */
    public function applyOnboardingScope(EloquentBuilder|QueryBuilder $q, string $table = 'onboarding_requests'): EloquentBuilder|QueryBuilder
    {
        if ($this->isAdmin()) {
            if ($this->overrideTrainerId !== null) {
                $q->where("{$table}.trainer_id", $this->overrideTrainerId);
            }

            return $q;
        }

        if ($this->isSale()) {
            $roster = $this->trainerIds;
            $self = $this->userId;

            return $q->where(function ($inner) use ($table, $roster, $self) {
                if (! empty($roster)) {
                    $inner->whereIn("{$table}.trainer_id", $roster);
                }
                // Include onboardings whose source appointment was created by this sale.
                $inner->orWhereExists(function ($sub) use ($table, $self) {
                    $sub->select(DB::raw(1))
                        ->from('appointments')
                        ->whereColumn('appointments.id', "{$table}.appointment_id")
                        ->where('appointments.creator_id', $self)
                        ->whereNull('appointments.deleted_at');
                });
            });
        }

        // trainer
        return $q->where("{$table}.trainer_id", $this->userId);
    }
}

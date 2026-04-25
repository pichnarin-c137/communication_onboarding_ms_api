<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingRequest extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'onboarding_requests';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'request_code',
        'appointment_id',
        'client_id',
        'trainer_id',
        'status',
        'progress_percentage',
        'completed_at',
        // Expansion columns
        'hold_reason',
        'hold_started_at',
        'hold_count',
        'revision_note',
        'revision_requested_at',
        'revision_requested_by_user_id',
        'due_date',
        'sla_breached_at',
        'parent_onboarding_id',
        'cycle_number',
        'reopened_at',
        'reopened_by_user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'appointment_id' => 'string',
        'client_id' => 'string',
        'trainer_id' => 'string',
        'revision_requested_by_user_id' => 'string',
        'parent_onboarding_id' => 'string',
        'reopened_by_user_id' => 'string',
        'progress_percentage' => 'float',
        'hold_count' => 'integer',
        'cycle_number' => 'integer',
        'completed_at' => 'datetime',
        'hold_started_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'reopened_at' => 'datetime',
        'due_date' => 'date',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function companyInfo(): HasOne
    {
        return $this->hasOne(OnboardingCompanyInfo::class, 'onboarding_id');
    }

    public function systemAnalysis(): HasOne
    {
        return $this->hasOne(OnboardingSystemAnalysis::class, 'onboarding_id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(OnboardingPolicy::class, 'onboarding_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(OnboardingLesson::class, 'onboarding_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'parent_onboarding_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OnboardingRequest::class, 'parent_onboarding_id');
    }

    public function linkedAppointments(): HasMany
    {
        return $this->hasMany(OnboardingAppointment::class, 'onboarding_id');
    }

    public function trainerAssignments(): HasMany
    {
        return $this->hasMany(OnboardingTrainerAssignment::class, 'onboarding_id');
    }

    public function clientFeedback(): HasOne
    {
        return $this->hasOne(OnboardingClientFeedback::class, 'onboarding_id');
    }

    public function feedbackToken(): HasOne
    {
        return $this->hasOne(OnboardingFeedbackToken::class, 'onboarding_id');
    }

    public function revisionRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revision_requested_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    public function getSaleAttribute(): ?User
    {
        return $this->appointment?->creator;
    }

    public function getSalesAttribute()
    {
        return $this->client?->sales?->sortByDesc('created_at')->values() ?? collect();
    }
}

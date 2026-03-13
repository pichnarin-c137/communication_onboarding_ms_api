<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'appointments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'appointment_type',
        'location_type',
        'status',
        'trainer_id',
        'client_id',
        'creator_id',
        'notes',
        'meeting_link',
        'physical_location',
        'scheduled_date',
        'scheduled_start_time',
        'scheduled_end_time',
        'actual_start_time',
        'actual_end_time',
        'start_proof_media_id',
        'end_proof_media_id',
        'start_lat',
        'start_lng',
        'end_lat',
        'end_lng',
        'leave_office_at',
        'leave_office_lat',
        'leave_office_lng',
        'student_count',
        'completion_notes',
        'cancellation_reason',
        'cancelled_by_user_id',
        'cancelled_at',
        'reschedule_reason',
        'is_onboarding_triggered',
        'is_continued_session',
        'related_onboarding_id',
    ];

    protected $casts = [
        'id' => 'string',
        'trainer_id' => 'string',
        'client_id' => 'string',
        'creator_id' => 'string',
        'start_proof_media_id' => 'string',
        'end_proof_media_id' => 'string',
        'cancelled_by_user_id' => 'string',
        'related_onboarding_id' => 'string',
        'scheduled_date' => 'date',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'leave_office_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'start_lat' => 'float',
        'start_lng' => 'float',
        'end_lat' => 'float',
        'end_lng' => 'float',
        'leave_office_lat' => 'float',
        'leave_office_lng' => 'float',
        'student_count' => 'integer',
        'is_onboarding_triggered' => 'boolean',
        'is_continued_session' => 'boolean',
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(AppointmentStudent::class, 'appointment_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(AppointmentMaterial::class, 'appointment_id');
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'related_onboarding_id');
    }

    public function startProof(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'start_proof_media_id');
    }

    public function endProof(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'end_proof_media_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}

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
        'appointment_code',
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
        'start_proof_media',
        'end_proof_media',
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
        'reschedule_at',
        'reschedule_to_date',
        'reschedule_to_start_time',
        'reschedule_to_end_time',
        'is_onboarding_triggered',
        'is_continued_session',
        'related_onboarding_id',
        'reminder_24h_sent_at',
        'reminder_1h_sent_at',
        'no_show_notified_at',
    ];

    protected $casts = [
        'id' => 'string',
        'trainer_id' => 'string',
        'client_id' => 'string',
        'creator_id' => 'string',
        'start_proof_media' => 'string',
        'end_proof_media' => 'string',
        'cancelled_by_user_id' => 'string',
        'related_onboarding_id' => 'string',
        'scheduled_date' => 'date:Y-m-d',
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
        'reschedule_reason' => 'string',
        'reschedule_at' => 'datetime',
        'reschedule_to_date' => 'string',
        'reschedule_to_start_time' => 'string',
        'reschedule_to_end_time' => 'string',
        'reminder_24h_sent_at' => 'datetime',
        'reminder_1h_sent_at' => 'datetime',
        'no_show_notified_at' => 'datetime',
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
        return $this->belongsTo(Media::class, 'start_proof_media');
    }

    public function endProof(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'end_proof_media');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}

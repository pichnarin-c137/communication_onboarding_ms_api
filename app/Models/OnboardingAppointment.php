<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingAppointment extends Model
{
    use HasUuids;

    protected $table = 'onboarding_appointments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'appointment_id',
        'session_type',
        'linked_by_user_id',
        'linked_at',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'appointment_id' => 'string',
        'linked_by_user_id' => 'string',
        'linked_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }
}

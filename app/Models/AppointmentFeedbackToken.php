<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentFeedbackToken extends Model
{
    use HasUuids;

    protected $table = 'appointment_feedback_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'appointment_id',
        'token',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'appointment_id' => 'string',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}

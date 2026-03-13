<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentStudent extends Model
{
    use HasUuids;

    protected $table = 'appointment_students';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'appointment_id',
        'name',
        'phone_number',
        'profession',
        'attendance_status',
    ];

    protected $casts = [
        'id' => 'string',
        'appointment_id' => 'string',
        'attendance_status' => 'string',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerActivityLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'trainer_id',
        'customer_id',
        'appointment_id',
        'status',
        'location',
        'accuracy',
        'speed',
        'detection_method',
    ];

    protected $casts = [
        'id' => 'string',
        'trainer_id' => 'string',
        'customer_id' => 'string',
        'appointment_id' => 'string',
        'accuracy' => 'float',
        'speed' => 'float',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'customer_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function scopeForTrainerToday($query, string $trainerId)
    {
        return $query->where('trainer_id', $trainerId)
            ->whereDate('created_at', now()->toDateString())
            ->orderBy('created_at');
    }

    public function scopeForTrainerOnDate($query, string $trainerId, string $date)
    {
        return $query->where('trainer_id', $trainerId)
            ->whereDate('created_at', $date)
            ->orderBy('created_at');
    }
}

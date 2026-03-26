<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerLocationPing extends Model
{
    use HasUuids;

    protected $fillable = [
        'trainer_id',
        'location',
        'accuracy',
        'speed',
        'pinged_at',
    ];

    protected $casts = [
        'id' => 'string',
        'trainer_id' => 'string',
        'accuracy' => 'float',
        'speed' => 'float',
        'pinged_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}

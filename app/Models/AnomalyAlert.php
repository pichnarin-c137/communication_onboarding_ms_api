<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyAlert extends Model
{
    use HasUuids;

    protected $fillable = [
        'trainer_id',
        'customer_id',
        'type',
        'severity',
        'details',
        'resolved',
        'resolved_at',
    ];

    protected $casts = [
        'id' => 'string',
        'trainer_id' => 'string',
        'customer_id' => 'string',
        'details' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
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

    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }
}

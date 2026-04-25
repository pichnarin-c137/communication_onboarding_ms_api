<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingTrainerAssignment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'onboarding_trainer_assignments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'trainer_id',
        'assigned_by_id',
        'assigned_at',
        'is_current',
        'replaced_at',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'trainer_id' => 'string',
        'assigned_by_id' => 'string',
        'is_current' => 'boolean',
        'assigned_at' => 'datetime',
        'replaced_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }
}

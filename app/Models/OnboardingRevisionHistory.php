<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingRevisionHistory extends Model
{
    use HasUuids;

    protected $table = 'onboarding_revision_history';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'note',
        'requested_by_user_id',
        'requested_at',
        'acknowledged_by_user_id',
        'acknowledged_at',
    ];

    protected $casts = [
        'id'                      => 'string',
        'onboarding_id'           => 'string',
        'requested_by_user_id'    => 'string',
        'acknowledged_by_user_id' => 'string',
        'requested_at'            => 'datetime',
        'acknowledged_at'         => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }
}

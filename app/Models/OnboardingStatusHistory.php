<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStatusHistory extends Model
{
    use HasUuids;

    protected $table = 'onboarding_status_history';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'from_status',
        'to_status',
        'changed_at',
        'changed_by_user_id',
        'reason',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'changed_by_user_id' => 'string',
        'changed_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}

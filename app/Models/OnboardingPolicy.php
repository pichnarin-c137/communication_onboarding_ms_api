<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingPolicy extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'onboarding_policies';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'policy_name',
        'is_default',
        'is_checked',
        'checked_at',
        'checked_by_user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'checked_by_user_id' => 'string',
        'is_default' => 'boolean',
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }
}

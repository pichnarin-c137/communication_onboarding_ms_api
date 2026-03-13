<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingCompanyInfo extends Model
{
    use HasUuids;

    protected $table = 'onboarding_company_info';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'content',
        'is_completed',
        'completed_at',
        'completed_by_user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'completed_by_user_id' => 'string',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}

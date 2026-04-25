<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingFeedbackToken extends Model
{
    use HasUuids;

    protected $table = 'onboarding_feedback_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'token',
        'client_email',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }
}

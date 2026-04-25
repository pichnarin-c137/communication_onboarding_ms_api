<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingClientFeedback extends Model
{
    use HasUuids;

    protected $table = 'onboarding_client_feedbacks';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'rating',
        'comment',
        'submitted_via',
        'submitted_by_user_id',
        'submitted_at',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'submitted_by_user_id' => 'string',
        'rating' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}

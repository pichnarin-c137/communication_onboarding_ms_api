<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingSystemAnalysis extends Model
{
    use HasUuids;

    protected $table = 'onboarding_system_analysis';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'import_employee_count',
        'connected_app_count',
        'profile_mobile_count',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'import_employee_count' => 'integer',
        'connected_app_count' => 'integer',
        'profile_mobile_count' => 'integer',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }
}

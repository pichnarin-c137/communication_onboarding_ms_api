<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingRequest extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'onboarding_requests';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'request_code',
        'appointment_id',
        'client_id',
        'trainer_id',
        'status',
        'progress_percentage',
        'completed_at',
    ];

    protected $casts = [
        'id' => 'string',
        'appointment_id' => 'string',
        'client_id' => 'string',
        'trainer_id' => 'string',
        'progress_percentage' => 'float',
        'completed_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function companyInfo(): HasOne
    {
        return $this->hasOne(OnboardingCompanyInfo::class, 'onboarding_id');
    }

    public function systemAnalysis(): HasOne
    {
        return $this->hasOne(OnboardingSystemAnalysis::class, 'onboarding_id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(OnboardingPolicy::class, 'onboarding_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(OnboardingLesson::class, 'onboarding_id');
    }
}

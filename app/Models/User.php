<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'dob',
        'address',
        'gender',
        'nationality',
        'is_suspended',
        'branch_id',
    ];

    protected $hidden = [
        // No password here - it's in credentials table
    ];

    protected $casts = [
        'id' => 'string',
        'role_id' => 'string',
        'branch_id' => 'string',
        'dob' => 'date:Y-m-d',
        'is_suspended' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    // Relationships
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function credential(): HasOne
    {
        return $this->hasOne(Credential::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function personalInformation(): HasOne
    {
        return $this->hasOne(PersonalInformation::class);
    }

    public function emergencyContact(): HasOne
    {
        return $this->hasOne(EmergencyContact::class);
    }

    public function oauthTokens(): HasMany
    {
        return $this->hasMany(OAuthToken::class);
    }

    public function systemAccesses(): HasMany
    {
        return $this->hasMany(UserSystemAccess::class);
    }

    public function systems(): BelongsToMany
    {
        return $this->belongsToMany(System::class, 'user_system_access')
            ->withPivot('granted_at', 'granted_by')
            ->using(UserSystemAccess::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    public function assignedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'assigned_sale_id');
    }

    public function salesCreated(): HasMany
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'trainer_id');
    }

    public function onboardingRequests(): HasMany
    {
        return $this->hasMany(OnboardingRequest::class, 'trainer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    // Helper methods
    public function isAdmin(): bool
    {
        return $this->role && $this->role->isAdmin();
    }

    public function isSuspended(): bool
    {
        return $this->is_suspended;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

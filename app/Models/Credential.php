<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'email',
        'username',
        'phone_number',
        'password',
        'otp',
        'otp_expiry',
    ];

    protected $hidden = [
        'password',
        'otp',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'otp_expiry' => 'datetime',
        'password' => 'hashed',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // OTP methods
    public function hasValidOtp(string $otp): bool
    {
        return $this->otp === $otp &&
               $this->otp_expiry &&
               Carbon::now()->lessThan($this->otp_expiry);
    }

    public function clearOtp(): void
    {
        $this->update([
            'otp' => null,
            'otp_expiry' => null,
        ]);
    }
}

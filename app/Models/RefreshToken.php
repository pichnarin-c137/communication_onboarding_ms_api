<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'token',
        'token_hash',
        'expires_at',
        'is_revoked',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'expires_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public function isValid(): bool
    {
        return ! $this->is_revoked && Carbon::now()->lessThan($this->expires_at);
    }

    public function revoke(): void
    {
        $this->update(['is_revoked' => true]);
    }
}

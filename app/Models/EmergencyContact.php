<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContact extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'emergency_contact';

    protected $fillable = [
        'user_id',
        'contact_first_name',
        'contact_last_name',
        'contact_relationship',
        'contact_phone_number',
        'contact_address',
        'contact_social_media',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper method for full name
    public function getFullNameAttribute(): string
    {
        return "{$this->contact_first_name} {$this->contact_last_name}";
    }
}

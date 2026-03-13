<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['role'];

    protected $casts = [
        'id' => 'string',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Helper methods
    public function isAdmin(): bool
    {
        return strtolower($this->role) === 'admin';
    }
}

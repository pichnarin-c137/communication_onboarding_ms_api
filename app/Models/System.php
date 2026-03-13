<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class System extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function userAccesses(): HasMany
    {
        return $this->hasMany(UserSystemAccess::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

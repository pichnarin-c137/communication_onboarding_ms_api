<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessType extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'business_types';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name_en',
        'name_km',
    ];

    protected $casts = [
        'id' => 'string',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'business_type_id');
    }
}

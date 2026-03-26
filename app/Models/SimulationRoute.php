<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SimulationRoute extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'from_label',
        'to_label',
        'waypoints',
        'duration_seconds',
        'distance_meters',
    ];

    protected $casts = [
        'id' => 'string',
        'waypoints' => 'array',
        'duration_seconds' => 'integer',
        'distance_meters' => 'integer',
    ];

    public $incrementing = false;

    protected $keyType = 'string';
}

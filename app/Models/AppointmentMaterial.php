<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppointmentMaterial extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'appointment_materials';

    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'media_id',
        'material_type',
        'description',
        'uploaded_at',
    ];

    protected $casts = [
        'id' => 'string',
        'appointment_id' => 'string',
        'media_id' => 'string',
        'uploaded_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}

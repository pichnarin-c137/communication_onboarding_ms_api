<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    use HasUuids;

    protected $table = 'media';

    protected $fillable = [
        'filename',
        'original_filename',
        'file_path',
        'file_url',
        'file_size',
        'mime_type',
        'media_category',
        'uploaded_by_user_id',
        'cloudinary_public_id',
    ];

    protected $casts = [
        'id' => 'string',
        'uploaded_by_user_id' => 'string',
        'file_size' => 'integer',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function appointmentMaterials(): HasMany
    {
        return $this->hasMany(AppointmentMaterial::class);
    }
}

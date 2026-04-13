<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'companies';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name_en',
        'name_km',
        'business_type_id',
        'owner_name_en',
        'owner_name_km',
        'phone',
        'address_km',
        'logo_media_id',
        'patent_document_media_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'id'                       => 'string',
        'business_type_id'         => 'string',
        'logo_media_id'            => 'string',
        'patent_document_media_id' => 'string',
        'created_by_user_id'       => 'string',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function patentDocument(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'patent_document_media_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

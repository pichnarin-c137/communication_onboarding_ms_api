<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'company_code',
        'company_name',
        'phone_number',
        'email',
        'headquarter_address',
        'social_links',
        'is_active',
        'assigned_sale_id',
        'banner_image_id',
        'logo_image_id',
    ];

    protected $casts = [
        'id' => 'string',
        'assigned_sale_id' => 'string',
        'banner_image_id' => 'string',
        'logo_image_id' => 'string',
        'social_links' => 'array',
        'is_active' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function assignedSale(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_sale_id');
    }

    public function bannerImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'banner_image_id');
    }

    public function logoImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_image_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function onboardingRequests(): HasMany
    {
        return $this->hasMany(OnboardingRequest::class);
    }
}

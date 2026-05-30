<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmContact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_name',
        'company_name_kh',
        'contact_name',
        'phone',
        'email',
        'address',
        'business_type_id',
        'source',
        'notes',
        'status',
        'synced_client_id',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'business_type_id' => 'string',
        'synced_client_id' => 'string',
        'created_by' => 'string',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'crm_contact_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'crm_contact_id');
    }

    public function syncedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'synced_client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

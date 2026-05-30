<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmDeal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'crm_contact_id',
        'title',
        'stage',
        'value',
        'expected_close_date',
        'notes',
        'assigned_to',
        'client_id',
        'won_at',
        'lost_at',
        'demo_completed_at',
        'lost_reason',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'crm_contact_id' => 'string',
        'assigned_to' => 'string',
        'client_id' => 'string',
        'created_by' => 'string',
        'value' => 'decimal:2',
        'expected_close_date' => 'date',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'demo_completed_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function demoAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'crm_deal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

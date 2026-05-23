<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleTrainerAssignment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'sale_trainer_assignments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sale_user_id',
        'trainer_user_id',
        'assigned_by_id',
        'assigned_at',
    ];

    protected $casts = [
        'id' => 'string',
        'sale_user_id' => 'string',
        'trainer_user_id' => 'string',
        'assigned_by_id' => 'string',
        'assigned_at' => 'datetime',
    ];

    public function saleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sale_user_id');
    }

    public function trainerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }
}

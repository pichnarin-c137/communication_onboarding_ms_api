<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasUuids;

    protected $table = 'sales';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sale_order_code',
        'client_id',
        'created_by',
        'content',
    ];

    protected $casts = [
        'id' => 'string',
        'sale_order_code' => 'string',
        'client_id' => 'string',
        'created_by' => 'string',
        'content' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

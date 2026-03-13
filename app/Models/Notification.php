<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'client_contact_id',
        'type',
        'title',
        'message',
        'related_entity_type',
        'related_entity_id',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'client_contact_id' => 'string',
        'related_entity_id' => 'string',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clientContact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TelegramGroup extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'client_id',
        'chat_id',
        'group_name',
        'bot_status',
        'language',
        'connected_by',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'id'               => 'string',
        'client_id'        => 'string',
        'connected_by'     => 'string',
        'connected_at'     => 'datetime',
        'disconnected_at'  => 'datetime',
        'language'         => 'string',
    ];

    // Relationships

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    // Scopes

    public function scopeConnected($query)
    {
        return $query->where('bot_status', 'connected');
    }

    public function scopeForClient($query, string $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}

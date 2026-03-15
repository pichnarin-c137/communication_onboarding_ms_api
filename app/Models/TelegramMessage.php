<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'telegram_group_id',
        'message_type',
        'message_body',
        'language',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'id'                => 'string',
        'telegram_group_id' => 'string',
        'sent_at'           => 'datetime',
    ];

    // Relationships

    public function telegramGroup(): BelongsTo
    {
        return $this->belongsTo(TelegramGroup::class);
    }

    // Scopes

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSentToday($query)
    {
        return $query->whereDate('sent_at', today());
    }
}

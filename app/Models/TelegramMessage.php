<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_contact_id',
        'message_type',
        'message_content',
        'telegram_message_id',
        'sent_at',
        'delivery_status',
        'error_message',
    ];

    protected $casts = [
        'id' => 'string',
        'client_contact_id' => 'string',
        'sent_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function clientContact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class);
    }

}

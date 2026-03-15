<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TelegramEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * telegram_events only has created_at — no updated_at.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'chat_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'id'      => 'string',
        'payload' => 'array',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientContact extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'name',
        'email',
        'phone_number',
        'telegram_username',
        'telegram_chat_id',
        'position',
        'is_primary_contact',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'client_id' => 'string',
        'is_primary_contact' => 'boolean',
        'is_active' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function telegramMessages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }
}

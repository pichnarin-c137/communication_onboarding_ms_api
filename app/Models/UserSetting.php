<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'in_app_notifications',
        'telegram_notifications',
        'language',
        'timezone',
        'items_per_page',
        'theme',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'id'                     => 'string',
        'user_id'                => 'string',
        'in_app_notifications'   => 'boolean',
        'telegram_notifications' => 'boolean',
        'items_per_page'         => 'integer',
        'quiet_hours_enabled'    => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInQuietHours(): bool
    {
        if (! $this->quiet_hours_enabled) {
            return false;
        }

        $now   = now($this->timezone)->format('H:i');
        $start = $this->quiet_hours_start;
        $end   = $this->quiet_hours_end;

        // Handle overnight ranges (e.g., 22:00 -> 07:00)
        if ($start > $end) {
            return $now >= $start || $now < $end;
        }

        return $now >= $start && $now < $end;
    }
}

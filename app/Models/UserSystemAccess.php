<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSystemAccess extends Model
{
    use HasUuids;

    protected $table = 'user_system_access';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'system_id',
        'granted_at',
        'granted_by',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'system_id' => 'string',
        'granted_by' => 'string',
        'granted_at' => 'datetime',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}

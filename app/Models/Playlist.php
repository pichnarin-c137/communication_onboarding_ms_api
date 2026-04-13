<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Playlist extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'playlists';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'description',
        'is_public',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id'         => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string',
        'is_public'  => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(PlaylistVideo::class, 'playlist_id')->orderBy('position');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(PlaylistSendLog::class, 'playlist_id');
    }
}

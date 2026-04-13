<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlaylistVideo extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'playlist_videos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'playlist_id',
        'title',
        'description',
        'youtube_url',
        'position',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id'          => 'string',
        'playlist_id' => 'string',
        'created_by'  => 'string',
        'updated_by'  => 'string',
        'deleted_by'  => 'string',
        'position'    => 'integer',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'playlist_id');
    }

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

    public function sendLogs(): HasMany
    {
        return $this->hasMany(PlaylistSendLog::class, 'video_id');
    }
}

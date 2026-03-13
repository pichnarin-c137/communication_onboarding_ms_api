<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'commentable_type',
        'commentable_id',
        'content',
        'is_internal',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'commentable_id' => 'string',
        'is_internal' => 'boolean',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

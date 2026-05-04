<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackRespondent extends Model
{
    use HasUuids;

    protected $table = 'feedback_respondents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'client_id',
        'name',
        'email',
        'phone_number',
        'position',
    ];

    protected $casts = [
        'id' => 'string',
        'client_id' => 'string',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

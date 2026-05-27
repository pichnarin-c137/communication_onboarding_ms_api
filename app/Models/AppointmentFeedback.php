<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentFeedback extends Model
{
    use HasUuids;

    protected $table = 'appointment_feedback';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'appointment_id',
        'token_id',
        'respondent_id',
        'rating',
        'comment',
        'submitted_at',
        'sentiment_score',
        'sentiment_label',
    ];

    protected $casts = [
        'id' => 'string',
        'appointment_id' => 'string',
        'token_id' => 'string',
        'respondent_id' => 'string',
        'rating' => 'integer',
        'submitted_at' => 'datetime',
        'sentiment_score' => 'float',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(AppointmentFeedbackToken::class, 'token_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(FeedbackRespondent::class, 'respondent_id');
    }
}

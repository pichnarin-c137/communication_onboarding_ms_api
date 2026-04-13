<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingLesson extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'onboarding_lessons';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'onboarding_id',
        'path',
        'slot_index',
        'lesson_document_id',
        'lesson_video_url',
        'is_sent',
        'sent_at',
        'sent_by_user_id',
        'telegram_message_id',
    ];

    protected $casts = [
        'id' => 'string',
        'onboarding_id' => 'string',
        'lesson_document_id' => 'string',
        'sent_by_user_id' => 'string',
        'telegram_message_id' => 'string',
        'path' => 'integer',
        'slot_index' => 'integer',
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(OnboardingRequest::class, 'onboarding_id');
    }

    public function lessonDocument(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'lesson_document_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function telegramMessage(): BelongsTo
    {
        return $this->belongsTo(TelegramMessage::class, 'telegram_message_id');
    }
}

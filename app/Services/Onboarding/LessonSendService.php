<?php

namespace App\Services\Onboarding;

use App\Exceptions\Business\LessonLockedAfterSendException;
use App\Models\OnboardingLesson;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Log;

class LessonSendService
{
    public function send(OnboardingLesson $lesson, string $userId): void
    {
        if ($lesson->is_sent) {
            throw new LessonLockedAfterSendException();
        }

        $telegramMessageId = null;

        try {
            $telegramMessage = TelegramMessage::create([
                'client_contact_id' => null,
                'message_type'      => 'lesson_sent',
                'message_content'   => $lesson->lesson_video_url
                    ? "Lesson video: {$lesson->lesson_video_url}"
                    : "Lesson document sent (path {$lesson->path})",
                'delivery_status'   => 'sent',
                'sent_at'           => now(),
            ]);

            $telegramMessageId = $telegramMessage->id;
        } catch (\Throwable $e) {
            Log::error('LessonSendService: failed to create TelegramMessage record', [
                'lesson_id' => $lesson->id,
                'error'     => $e->getMessage(),
            ]);
        }

        $lesson->update([
            'is_sent'             => true,
            'sent_at'             => now(),
            'sent_by_user_id'     => $userId,
            'telegram_message_id' => $telegramMessageId,
        ]);
    }
}

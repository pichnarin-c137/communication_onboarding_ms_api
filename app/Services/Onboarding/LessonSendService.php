<?php

namespace App\Services\Onboarding;

use App\Exceptions\Business\LessonLockedAfterSendException;
use App\Models\OnboardingLesson;
use App\Services\Telegram\TelegramGroupService;
use Illuminate\Support\Facades\Log;

class LessonSendService
{
    public function __construct(
        private TelegramGroupService $telegramGroupService,
    ) {}

    public function send(OnboardingLesson $lesson, string $userId): void
    {
        if ($lesson->is_sent) {
            throw new LessonLockedAfterSendException;
        }

        // Mark the lesson as sent. The telegram_message_id FK will be populated
        // by the Telegram hook below once the group message is queued.
        $lesson->update([
            'is_sent' => true,
            'sent_at' => now(),
            'sent_by_user_id' => $userId,
            'telegram_message_id' => null,
        ]);

        // Telegram hook: notify client group that a lesson has been sent
        try {
            $onboarding = $lesson->onboarding;
            $clientId = $onboarding?->client_id;
            $clientName = $onboarding?->client?->company_name ?? 'Client';
            $lessonUrl = $lesson->lesson_video_url;
            $lessonName = $lesson->lesson_video_url
                ? "Lesson Path {$lesson->path} (Video)"
                : "Lesson Path {$lesson->path} (Document)";

            if ($clientId) {
                $this->telegramGroupService->notifyClient($clientId, 'lesson_sent', [
                    'client_name' => $clientName,
                    'lesson_name' => $lessonName,
                    'lesson_url' => $lessonUrl,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('LessonSendService Telegram notification failed', [
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

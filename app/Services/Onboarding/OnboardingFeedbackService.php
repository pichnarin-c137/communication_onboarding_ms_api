<?php

namespace App\Services\Onboarding;

use App\Mail\OnboardingFeedbackMailable;
use App\Models\OnboardingClientFeedback;
use App\Models\OnboardingFeedbackToken;
use App\Models\OnboardingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Random\RandomException;
use Throwable;

class OnboardingFeedbackService
{
    /**
     * @throws RandomException
     */
    public function requestFeedback(OnboardingRequest $onboarding): void
    {
        $clientEmail = $onboarding->client?->email;

        if (! $clientEmail) {
            throw ValidationException::withMessages([
                'email' => ['Client does not have an email address on file.'],
            ]);
        }

        // Invalidate any existing unused token
        OnboardingFeedbackToken::where('onboarding_id', $onboarding->id)
            ->whereNull('used_at')
            ->delete();

        // Generate token: store SHA-256 hash in DB, send raw token in email
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $ttlDays = config('coms.feedback_token_ttl_days', 7);

        OnboardingFeedbackToken::create([
            'onboarding_id' => $onboarding->id,
            'token' => $hashedToken,
            'client_email' => $clientEmail,
            'expires_at' => now()->addDays($ttlDays),
        ]);

        try {
            Mail::to($clientEmail)->queue(
                new OnboardingFeedbackMailable($onboarding, $rawToken, $clientEmail)
            );
        } catch (Throwable $e) {
            Log::error('OnboardingFeedbackService: failed to queue feedback email', [
                'onboarding_id' => $onboarding->id,
                'error' => $e->getMessage(),
            ]);
        }

        Cache::store('redis')->forget("onboarding:show:$onboarding->id");
    }

    /**
     * @throws Throwable
     */
    public function submitViaEmail(string $rawToken, int $rating, ?string $comment): OnboardingClientFeedback
    {
        $hashedToken = hash('sha256', $rawToken);

        $tokenRecord = OnboardingFeedbackToken::where('token', $hashedToken)->first();

        if (! $tokenRecord) {
            throw ValidationException::withMessages([
                'token' => ['Invalid feedback token.'],
            ]);
        }

        if ($tokenRecord->used_at !== null) {
            throw ValidationException::withMessages([
                'token' => ['This feedback link has already been used.'],
            ]);
        }

        if ($tokenRecord->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This feedback link has expired.'],
            ]);
        }

        $feedback = DB::transaction(function () use ($tokenRecord, $rating, $comment) {
            $feedback = OnboardingClientFeedback::create([
                'onboarding_id' => $tokenRecord->onboarding_id,
                'rating' => $rating,
                'comment' => $comment,
                'submitted_via' => 'email',
                'submitted_at' => now(),
            ]);

            $tokenRecord->update(['used_at' => now()]);

            return $feedback;
        });

        Cache::store('redis')->forget("onboarding:show:$tokenRecord->onboarding_id");

        return $feedback;
    }

    public function submitManual(OnboardingRequest $onboarding, int $rating, ?string $comment, string $userId): OnboardingClientFeedback
    {
        if ($onboarding->clientFeedback) {
            throw ValidationException::withMessages([
                'feedback' => ['Feedback has already been submitted for this onboarding.'],
            ]);
        }

        $feedback = OnboardingClientFeedback::create([
            'onboarding_id' => $onboarding->id,
            'rating' => $rating,
            'comment' => $comment,
            'submitted_via' => 'manual',
            'submitted_by_user_id' => $userId,
            'submitted_at' => now(),
        ]);

        Cache::store('redis')->forget("onboarding:show:$onboarding->id");

        return $feedback;
    }

    public function getFeedback(OnboardingRequest $onboarding): array
    {
        $feedback = $onboarding->clientFeedback;

        if ($feedback) {
            return [
                'status' => 'submitted',
                'rating' => $feedback->rating,
                'comment' => $feedback->comment,
                'submitted_at' => $feedback->submitted_at,
            ];
        }

        $token = $onboarding->feedbackToken;

        if ($token && $token->used_at === null && $token->expires_at->isFuture()) {
            return [
                'status' => 'sent',
                'sent_at' => $token->created_at,
            ];
        }

        return ['status' => 'not_requested'];
    }
}

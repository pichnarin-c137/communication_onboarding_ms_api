<?php

namespace App\Observers;

use App\Services\Analytics\Support\SentimentClassifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Compute and persist comment sentiment when feedback is created via Eloquent.
 * Attached to OnboardingClientFeedback and AppointmentFeedback.
 *
 * Historical rows and raw `DB::table()->insert()` rows (e.g. the analytics demo
 * seeder) bypass observers — those are filled by `analytics:backfill-sentiment`.
 *
 * Fails open: a classifier error must never block a feedback write.
 */
class FeedbackSentimentObserver
{
    public function __construct(private SentimentClassifier $classifier) {}

    public function creating(Model $model): void
    {
        // Don't overwrite a score that was explicitly provided.
        if ($model->sentiment_score !== null) {
            return;
        }

        try {
            $result = $this->classifier->classify((string) $model->comment, $model->rating);
            $model->sentiment_score = $result['score'];
            $model->sentiment_label = $result['label'];
        } catch (Throwable $e) {
            Log::warning('feedback_sentiment.classify_failed', [
                'model' => $model::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist comment sentiment on both feedback tables so /analytics/sentiment
 * only aggregates stored values instead of re-classifying text on every call.
 *
 * Populated by:
 *   - FeedbackSentimentObserver on new writes (Eloquent create), and
 *   - `php artisan analytics:backfill-sentiment` for historical / raw-inserted rows.
 *
 *   sentiment_score: [-1, 1] mean-comparable float (null = not analyzed)
 *   sentiment_label: 'positive' | 'neutral' | 'negative' (null = not analyzed)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['onboarding_client_feedbacks', 'appointment_feedback'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('sentiment_score', 4, 3)->nullable()->after('comment');
                $t->string('sentiment_label', 12)->nullable()->after('sentiment_score');
                $t->index(['sentiment_label'], "{$t->getTable()}_sentiment_label_idx");
            });
        }
    }

    public function down(): void
    {
        foreach (['onboarding_client_feedbacks', 'appointment_feedback'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex("{$table}_sentiment_label_idx");
                $t->dropColumn(['sentiment_score', 'sentiment_label']);
            });
        }
    }
};

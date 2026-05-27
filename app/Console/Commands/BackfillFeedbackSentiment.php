<?php

namespace App\Console\Commands;

use App\Services\Analytics\Support\SentimentClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populate sentiment_score / sentiment_label on feedback rows that don't have it
 * yet — historical data and rows inserted raw (analytics demo seeder).
 *
 *   php artisan analytics:backfill-sentiment            # only null sentiment rows
 *   php artisan analytics:backfill-sentiment --fresh    # recompute every row
 */
class BackfillFeedbackSentiment extends Command
{
    protected $signature = 'analytics:backfill-sentiment {--fresh : Recompute sentiment for every row, not just un-analyzed ones}';

    protected $description = 'Classify and persist comment sentiment on feedback tables for /analytics/sentiment.';

    public function handle(SentimentClassifier $classifier): int
    {
        $fresh = (bool) $this->option('fresh');
        $total = 0;

        foreach (['onboarding_client_feedbacks', 'appointment_feedback'] as $table) {
            $count = $this->backfillTable($table, $classifier, $fresh);
            $this->info("{$table}: {$count} row(s) classified.");
            $total += $count;
        }

        $this->info("Done. {$total} row(s) updated. Remember to clear the analytics cache.");

        return self::SUCCESS;
    }

    private function backfillTable(string $table, SentimentClassifier $classifier, bool $fresh): int
    {
        $updated = 0;

        DB::table($table)
            ->when(! $fresh, fn ($q) => $q->whereNull('sentiment_score'))
            ->orderBy('id')
            ->select('id', 'comment', 'rating')
            ->chunkById(500, function ($rows) use ($table, $classifier, &$updated) {
                foreach ($rows as $row) {
                    $result = $classifier->classify((string) ($row->comment ?? ''), $row->rating !== null ? (int) $row->rating : null);

                    DB::table($table)->where('id', $row->id)->update([
                        'sentiment_score' => $result['score'],
                        'sentiment_label' => $result['label'],
                    ]);
                    $updated++;
                }
            });

        return $updated;
    }
}

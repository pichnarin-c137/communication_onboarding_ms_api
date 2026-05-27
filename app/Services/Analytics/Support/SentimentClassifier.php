<?php

namespace App\Services\Analytics\Support;

/**
 * Lexicon + rating-assisted sentiment classifier.
 *
 * Comments are mixed Khmer + English, so a pure English lexicon under-reads
 * Khmer text. Two cheap, deterministic signals are blended:
 *
 *   1. Lexicon score — (#positive − #negative keyword hits) / (#hits), in [-1,1].
 *      Keyword lists cover English AND common Khmer feedback terms.
 *   2. Rating score  — (rating − 3) / 2, mapping 1→-1, 3→0, 5→+1.
 *
 * When the comment matches at least one lexicon keyword the two are blended
 * (0.6 lexicon / 0.4 rating); otherwise the rating alone decides. This keeps
 * Khmer-only comments sensible (the star rating carries them) while letting
 * explicit wording override a generous rating ("5 stars but trainer was late").
 *
 * The 'llm' provider is documented but not wired to an external model in this
 * build; classify() falls back to the lexicon so the endpoint always returns
 * deterministic, dependency-light results. Swap the provider here when an LLM
 * batch path is added.
 */
final class SentimentClassifier
{
    private const POSITIVE_THRESHOLD = 0.15;

    private const NEGATIVE_THRESHOLD = -0.15;

    /** English + Khmer positive cues (lowercased; Khmer is case-insensitive). */
    private const POSITIVE = [
        'good', 'great', 'excellent', 'helpful', 'patient', 'clear', 'friendly',
        'professional', 'knowledgeable', 'thorough', 'kind', 'fast', 'quick',
        'smooth', 'easy', 'love', 'loved', 'perfect', 'amazing', 'awesome',
        'satisfied', 'happy', 'recommend', 'responsive', 'punctual', 'organized',
        'wonderful', 'nice', 'best', 'impressed', 'attentive', 'supportive',
        // Khmer
        'ល្អ', 'ល្អណាស់', 'ពូកែ', 'ស្អាត', 'ច្បាស់', 'អស្ចារ្យ', 'ពេញចិត្ត', 'រួសរាយ',
        'ងាយស្រួល', 'លឿន', 'ទាន់ពេល', 'ស្ងប់ស្ងាត់', 'អត់ធ្មត់',
    ];

    /** English + Khmer negative cues. */
    private const NEGATIVE = [
        'bad', 'poor', 'late', 'slow', 'rude', 'confusing', 'unclear', 'unhelpful',
        'disappointed', 'disappointing', 'terrible', 'awful', 'worst', 'horrible',
        'unprofessional', 'rushed', 'impatient', 'no-show', 'noshow', 'absent',
        'cancelled', 'delay', 'delayed', 'problem', 'issue', 'complaint', 'angry',
        'frustrated', 'waiting', 'waited', 'missing', 'never', 'failed', 'wrong',
        'difficult', 'hard', 'unresponsive',
        // Khmer
        'អាក្រក់', 'យឺត', 'យឺតពេល', 'មិនល្អ', 'មិនច្បាស់', 'ច្របូកច្របល់', 'ខកចិត្ត',
        'ឈ្លើយ', 'មិនពេញចិត្ត', 'បញ្ហា', 'រង់ចាំ', 'អត់មក', 'លំបាក',
    ];

    /**
     * Theme keyword map (key => cues). A comment "mentions" a theme when any cue
     * appears. Used by /analytics/sentiment to surface recurring topics.
     *
     * @var array<string,list<string>>
     */
    private const THEMES = [
        'punctuality'     => ['late', 'punctual', 'on time', 'on-time', 'delay', 'delayed', 'waiting', 'waited', 'no-show', 'noshow', 'absent', 'យឺត', 'ទាន់ពេល', 'រង់ចាំ', 'អត់មក'],
        'patience'        => ['patient', 'impatient', 'rushed', 'calm', 'អត់ធ្មត់', 'ស្ងប់ស្ងាត់'],
        'clarity'         => ['clear', 'unclear', 'confusing', 'explain', 'explained', 'understand', 'ច្បាស់', 'មិនច្បាស់', 'ច្របូកច្របល់'],
        'knowledge'       => ['knowledgeable', 'expert', 'skilled', 'knew', 'knowledge', 'professional', 'ពូកែ', 'ចេះ'],
        'responsiveness'  => ['responsive', 'unresponsive', 'reply', 'replied', 'answer', 'answered', 'support', 'supportive', 'help', 'helpful', 'ជួយ', 'ឆ្លើយ'],
        'friendliness'    => ['friendly', 'kind', 'rude', 'polite', 'nice', 'warm', 'រួសរាយ', 'ឈ្លើយ'],
        'pace'            => ['fast', 'quick', 'slow', 'pace', 'speed', 'rushed', 'លឿន', 'យឺត'],
        'materials'       => ['material', 'materials', 'document', 'documents', 'slide', 'slides', 'content', 'ឯកសារ'],
    ];

    /**
     * @return array{score: float, label: string}
     */
    public function classify(string $comment, ?int $rating = null): array
    {
        $text = mb_strtolower(trim($comment));

        if ($text === '') {
            $score = $this->ratingScore($rating);

            return ['score' => $score, 'label' => $this->label($score)];
        }

        $pos = $this->countHits($text, self::POSITIVE);
        $neg = $this->countHits($text, self::NEGATIVE);
        $hits = $pos + $neg;

        $ratingScore = $this->ratingScore($rating);

        if ($hits === 0) {
            $score = $ratingScore;
        } else {
            $lexScore = ($pos - $neg) / $hits;
            $score = $rating === null
                ? $lexScore
                : (0.6 * $lexScore) + (0.4 * $ratingScore);
        }

        $score = max(-1.0, min(1.0, round($score, 3)));

        return ['score' => $score, 'label' => $this->label($score)];
    }

    /**
     * Themes mentioned in a comment.
     *
     * @return list<string>
     */
    public function detectThemes(string $comment): array
    {
        $text = mb_strtolower($comment);
        $found = [];

        foreach (self::THEMES as $theme => $cues) {
            foreach ($cues as $cue) {
                if (mb_strpos($text, $cue) !== false) {
                    $found[] = $theme;
                    break;
                }
            }
        }

        return $found;
    }

    public function label(float $score): string
    {
        return match (true) {
            $score > self::POSITIVE_THRESHOLD => 'positive',
            $score < self::NEGATIVE_THRESHOLD => 'negative',
            default                           => 'neutral',
        };
    }

    private function ratingScore(?int $rating): float
    {
        if ($rating === null) {
            return 0.0;
        }

        return max(-1.0, min(1.0, ($rating - 3) / 2));
    }

    private function countHits(string $text, array $words): int
    {
        $count = 0;
        foreach ($words as $word) {
            // Latin words: word-boundary match to avoid "later" matching "late".
            // Khmer has no spaces/word boundaries, so substring match is used.
            if ($this->isLatin($word)) {
                $count += preg_match_all('/\b'.preg_quote($word, '/').'\b/u', $text);
            } elseif (mb_strpos($text, $word) !== false) {
                $count++;
            }
        }

        return $count;
    }

    private function isLatin(string $word): bool
    {
        return (bool) preg_match('/^[a-z0-9\s\-]+$/', $word);
    }
}

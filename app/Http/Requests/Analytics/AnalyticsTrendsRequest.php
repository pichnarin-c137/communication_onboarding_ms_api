<?php

namespace App\Http\Requests\Analytics;

use App\Exceptions\Analytics\InvalidMetricException;

class AnalyticsTrendsRequest extends AnalyticsFilterRequest
{
    public const ALLOWED_METRICS = [
        'appointments_created',
        'appointments_completed',
        'completion_rate',
        'cancellation_rate',
        'onboardings_created',
        'onboardings_completed',
        'avg_rating',
        'feedback_count',
    ];

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $metric = $this->query('metric');

        if ($metric === null || $metric === '') {
            throw new InvalidMetricException(
                'Query parameter `metric` is required.',
                0,
                null,
                ['allowed' => self::ALLOWED_METRICS],
            );
        }

        if (! in_array($metric, self::ALLOWED_METRICS, true)) {
            throw new InvalidMetricException(
                "Metric `{$metric}` is not in the allowed set.",
                0,
                null,
                ['allowed' => self::ALLOWED_METRICS, 'received' => $metric],
            );
        }
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'metric' => ['required', 'string'],
        ]);
    }
}

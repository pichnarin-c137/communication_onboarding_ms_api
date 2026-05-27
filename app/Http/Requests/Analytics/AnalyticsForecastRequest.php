<?php

namespace App\Http\Requests\Analytics;

use App\Exceptions\Analytics\InvalidMetricException;
use App\Services\Analytics\AnalyticsForecastService;

class AnalyticsForecastRequest extends AnalyticsFilterRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $metric = $this->query('metric');
        $allowed = AnalyticsForecastService::allowedMetrics();

        // metric is optional (service defaults to onboardings_completed) but must
        // be valid when supplied.
        if ($metric !== null && $metric !== '' && ! in_array($metric, $allowed, true)) {
            throw new InvalidMetricException(
                "Metric `{$metric}` is not forecastable.",
                0,
                null,
                ['allowed' => $allowed, 'received' => $metric],
            );
        }
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'metric'  => ['nullable', 'string'],
            'horizon' => ['nullable', 'integer', 'min:1', 'max:24'],
            'method'  => ['nullable', 'in:holt,linear,moving_avg'],
        ]);
    }
}

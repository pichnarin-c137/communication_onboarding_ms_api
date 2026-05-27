<?php

namespace App\Http\Requests\Analytics;

class AnalyticsCohortsRequest extends AnalyticsFilterRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'cohort_by'   => ['nullable', 'in:month,week'],
            'max_elapsed' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);
    }
}

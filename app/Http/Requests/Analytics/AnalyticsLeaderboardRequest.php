<?php

namespace App\Http\Requests\Analytics;

class AnalyticsLeaderboardRequest extends AnalyticsFilterRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'sort'     => ['nullable', 'string'],
            'order'    => ['nullable', 'in:asc,desc'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}

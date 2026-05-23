<?php

namespace App\Http\Requests\Analytics;

use App\Exceptions\Analytics\InvalidDateFormatException;
use App\Exceptions\Analytics\InvalidDateOrderException;
use App\Exceptions\Analytics\RangeTooLargeException;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Run guard checks BEFORE Laravel validation so we can throw typed
     * domain exceptions (INVALID_DATE_FORMAT, INVALID_DATE_ORDER,
     * RANGE_TOO_LARGE) instead of the generic 422 VALIDATION_ERROR path.
     */
    protected function prepareForValidation(): void
    {
        $from = $this->query('from');
        $to = $this->query('to');

        if ($from === null || $to === null) {
            return;
        }

        if (! $this->isIsoDate($from) || ! $this->isIsoDate($to)) {
            throw new InvalidDateFormatException(
                'Dates must be in YYYY-MM-DD format.',
                0,
                null,
                ['from' => $from, 'to' => $to],
            );
        }

        $tz = config('coms.analytics.business_timezone', 'Asia/Phnom_Penh');
        $fromDt = Carbon::createFromFormat('Y-m-d', $from, $tz)->startOfDay();
        $toDt = Carbon::createFromFormat('Y-m-d', $to, $tz)->endOfDay();

        if ($fromDt->gt($toDt)) {
            throw new InvalidDateOrderException(
                '`from` must be on or before `to`.',
                0,
                null,
                ['from' => $from, 'to' => $to],
            );
        }

        $maxDays = (int) config('coms.analytics.max_range_days', 365);
        if ((int) $fromDt->copy()->startOfDay()->diffInDays($toDt->copy()->startOfDay()) > $maxDays) {
            throw new RangeTooLargeException(
                "Requested range exceeds the {$maxDays}-day maximum.",
                0,
                null,
                ['max_range_days' => $maxDays, 'from' => $from, 'to' => $to],
            );
        }
    }

    public function rules(): array
    {
        return [
            'from'    => ['required', 'date_format:Y-m-d'],
            'to'      => ['required', 'date_format:Y-m-d'],
            'compare' => ['nullable', 'in:prev,yoy,none'],
            'group_by' => ['nullable', 'in:day,week,month'],
            'trainer_id'       => ['nullable', 'uuid'],
            'sale_id'          => ['nullable', 'uuid'],
            'business_type_id' => ['nullable', 'uuid'],
            'system_id'        => ['nullable', 'uuid'],
            'location_type'    => ['nullable', 'in:online,physical,hybrid'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'error_code' => 'VALIDATION_ERROR',
            'errors' => $validator->errors(),
        ], 422));
    }

    private function isIsoDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $d = Carbon::createFromFormat('Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }
}

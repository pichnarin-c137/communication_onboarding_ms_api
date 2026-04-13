<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $languages = implode(',', config('coms.user_settings.supported_languages', ['en', 'km']));
        $themes    = implode(',', config('coms.user_settings.supported_themes', ['light', 'dark']));
        $min       = config('coms.user_settings.items_per_page_min', 5);
        $max       = config('coms.user_settings.items_per_page_max', 100);

        return [
            'in_app_notifications'   => ['sometimes', 'boolean'],
            'telegram_notifications' => ['sometimes', 'boolean'],
            'language'               => ['sometimes', 'string', "in:{$languages}"],
            'timezone'               => ['sometimes', 'string', 'timezone'],
            'items_per_page'         => ['sometimes', 'integer', "min:{$min}", "max:{$max}"],
            'theme'                  => ['sometimes', 'string', "in:{$themes}"],
            'quiet_hours_enabled'    => ['sometimes', 'boolean'],
            'quiet_hours_start'      => ['sometimes', 'date_format:H:i'],
            'quiet_hours_end'        => ['sometimes', 'date_format:H:i'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors'  => $validator->errors(),
        ], 422));
    }
}

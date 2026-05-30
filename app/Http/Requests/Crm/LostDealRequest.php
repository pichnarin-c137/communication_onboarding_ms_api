<?php

namespace App\Http\Requests\Crm;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LostDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'error_code' => 'VALIDATION_FAILED',
            'errors' => $validator->errors(),
        ], 422));
    }
}

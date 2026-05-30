<?php

namespace App\Http\Requests\Crm;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateCrmDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            // Terminal stages (won/lost) are only reachable via /won and /lost endpoints.
            'stage' => ['sometimes', 'required', 'string', Rule::in(config('coms.crm.deal_editable_stages'))],
            'value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
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

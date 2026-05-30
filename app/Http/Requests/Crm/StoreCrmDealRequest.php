<?php

namespace App\Http\Requests\Crm;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreCrmDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'uuid', 'exists:crm_contacts,id'],
            'title' => ['required', 'string', 'max:255'],
            // New deals may only start in a non-terminal stage; won/lost go through their own endpoints.
            'stage' => ['nullable', 'string', Rule::in(config('coms.crm.deal_editable_stages'))],
            'value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
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

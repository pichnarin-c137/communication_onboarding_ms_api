<?php

namespace App\Http\Requests\Crm;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateCrmContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'company_name_kh' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'business_type_id' => ['sometimes', 'nullable', 'uuid', 'exists:business_types,id'],
            'source' => ['sometimes', 'nullable', 'string', Rule::in(config('coms.crm.deal_sources'))],
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

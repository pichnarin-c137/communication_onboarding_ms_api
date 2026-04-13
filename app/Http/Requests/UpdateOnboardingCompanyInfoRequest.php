<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOnboardingCompanyInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'                => ['nullable', 'string', 'json'],
            'is_completed'           => ['nullable', 'boolean'],
            'logo_base64'            => ['nullable', 'string', 'regex:/^data:image\/(jpeg|png|webp);base64,/'],
            'patent_document_base64' => ['nullable', 'string', 'regex:/^data:image\/(jpeg|png|webp);base64,/'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors(),
        ], 422));
    }
}

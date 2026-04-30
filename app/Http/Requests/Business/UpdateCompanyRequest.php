<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['sometimes', 'string', 'max:255'],
            'name_km' => ['sometimes', 'string', 'max:255'],
            'business_type_id' => ['sometimes', 'uuid', 'exists:business_types,id'],
            'owner_name_en' => ['sometimes', 'string', 'max:255'],
            'owner_name_km' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'address_km' => ['sometimes', 'string', 'max:1000'],
            'logo_media_id' => ['nullable', 'uuid', 'exists:media,id'],
            'patent_document_media_id' => ['nullable', 'uuid', 'exists:media,id'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}

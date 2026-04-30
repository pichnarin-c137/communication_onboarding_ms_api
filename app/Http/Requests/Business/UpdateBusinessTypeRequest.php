<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateBusinessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name_en' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('business_types', 'name_en')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'name_km' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('business_types', 'name_km')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
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

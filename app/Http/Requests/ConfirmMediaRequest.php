<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConfirmMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key'               => ['required', 'string', 'max:500'],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'mime_type'         => ['nullable', 'string', 'max:100'],
            'file_size'         => ['nullable', 'integer', 'min:0'],
            'category'          => ['nullable', 'string', 'in:profile,logo,banner,document,other'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'    => false,
            'message'    => 'Validation errors',
            'error_code' => 'VALIDATION_ERROR',
            'errors'     => $validator->errors(),
        ], 422));
    }
}

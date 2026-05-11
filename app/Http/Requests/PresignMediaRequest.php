<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PresignMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filename'  => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp,image/gif,application/pdf'],
            'folder'    => ['nullable', 'string', 'in:start_proof,end_proof,logos,patent,uploads'],
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

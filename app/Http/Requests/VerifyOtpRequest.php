<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $otpLength = (int) config('otp.length', 6);

        return [
            'identifier' => ['required', 'string'],
            'otp' => ['required', 'string', 'digits:' . $otpLength],
            'remember_me' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'string', 'timezone'],
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

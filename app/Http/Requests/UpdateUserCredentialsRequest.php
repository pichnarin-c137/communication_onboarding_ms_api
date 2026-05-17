<?php

namespace App\Http\Requests;

use App\Models\Credential;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('userId');

        $credential = Credential::where('user_id', $userId)->first();
        $credentialId = $credential?->id;

        return [
            'email' => ['sometimes', 'email', 'max:255', "unique:credentials,email,{$credentialId},id"],
            'username' => ['sometimes', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', "unique:credentials,username,{$credentialId},id"],
            'phone_number' => ['sometimes', 'string', 'regex:/^\+?[1-9]\d{1,14}$/', "unique:credentials,phone_number,{$credentialId},id"],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors(),
        ], 422));
    }
}

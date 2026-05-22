<?php

namespace App\Http\Requests\Sale;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReplaceSaleRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $min = (int) config('coms.sale_roster.min_trainers', 1);

        return [
            'trainer_ids' => ['required', 'array', "min:$min"],
            'trainer_ids.*' => ['required', 'uuid', 'distinct', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        $min = (int) config('coms.sale_roster.min_trainers', 1);

        return [
            'trainer_ids.required' => 'At least one dedicated trainer must be provided.',
            'trainer_ids.min' => "A sale user must have at least $min dedicated trainer(s).",
            'trainer_ids.*.uuid' => 'Each trainer id must be a valid UUID.',
            'trainer_ids.*.distinct' => 'Duplicate trainer ids are not allowed.',
            'trainer_ids.*.exists' => 'One or more trainer ids do not match an existing user.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'error_code' => 'VALIDATION_FAILED',
            'errors' => $validator->errors(),
        ], 422));
    }
}

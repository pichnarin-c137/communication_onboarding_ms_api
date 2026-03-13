<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AddStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'students' => ['required', 'array'],
            'students.*.name' => ['nullable', 'string', 'max:255'],
            'students.*.phone_number' => ['nullable', 'string', 'max:20'],
            'students.*.profession' => ['nullable', 'string', 'max:100'],
            'students.*.attendance_status' => ['nullable', 'in:present,absent'],
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

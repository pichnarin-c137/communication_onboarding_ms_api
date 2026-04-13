<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompleteAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_proof_media' => [
                'required', 
                'string', 
                'regex:/^data:image\/(jpeg|png|webp);base64,/'
            ], 
            'end_latitude' => ['required', 'numeric', 'between:-90,90'],
            'end_longitude' => ['required', 'numeric', 'between:-180,180'],
            'student_count' => ['required', 'integer', 'min:0'],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
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

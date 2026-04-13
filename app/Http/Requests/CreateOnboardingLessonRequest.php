<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateOnboardingLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'integer', 'in:1,2,3'],
            'slot_index' => ['nullable', 'integer', 'min:1'],
            'lesson_document_id' => ['nullable', 'uuid', 'exists:media,id'],
            'lesson_video_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->input('lesson_document_id') && ! $this->input('lesson_video_url')) {
                $v->errors()->add('lesson_document_id', 'Either a lesson document or lesson video URL is required.');
            }
        });
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

<?php

namespace App\Http\Requests\Playlist;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AddPlaylistVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'youtube_url' => [
                'required',
                'string',
                'regex:/^https:\/\/(?:www\.|m\.)?(?:youtube\.com\/watch\?.+|youtu\.be\/.+)$/',
            ],
            'position' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'youtube_url.regex' => 'The YouTube URL must be a valid https://www.youtube.com/watch?... or https://youtu.be/... link.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $message = $errors->first() ?: 'Validation errors.';

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'VALIDATION_ERROR',
            'errors' => $errors,
        ], 422));
    }
}

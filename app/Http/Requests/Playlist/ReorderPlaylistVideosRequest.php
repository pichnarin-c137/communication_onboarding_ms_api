<?php

namespace App\Http\Requests\Playlist;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReorderPlaylistVideosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'videos'            => ['required', 'array', 'min:1'],
            'videos.*.id'       => ['required', 'string', 'exists:playlist_videos,id'],
            'videos.*.position' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'videos.required'               => 'A videos array is required.',
            'videos.*.id.exists'            => 'One or more video IDs do not exist.',
            'videos.*.position.required'    => 'Each video entry must have a position.',
            'videos.*.position.integer'     => 'Position must be an integer.',
            'videos.*.position.min'         => 'Position must be at least 1.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success'    => false,
            'message'    => 'Validation errors.',
            'error_code' => 'VALIDATION_ERROR',
            'errors'     => $validator->errors(),
        ], 422));
    }
}

<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class ReassignTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trainer_id' => ['required', 'uuid', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

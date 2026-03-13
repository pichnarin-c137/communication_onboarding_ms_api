<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                => ['required_if:appointment_type,demo', 'nullable', 'string', 'max:255'],
            'appointment_type'     => ['required', 'in:training,demo'],
            'location_type'        => ['required', 'in:physical,online,hybrid'],
            'client_id'            => ['required', 'uuid', 'exists:clients,id'],
            'trainer_id'           => ['nullable', 'uuid', 'exists:users,id'],
            'scheduled_date'       => ['required', 'date'],
            'scheduled_start_time' => ['required', 'date_format:H:i'],
            'scheduled_end_time'   => ['required', 'date_format:H:i', 'after:scheduled_start_time'],
            'meeting_link'         => ['nullable', 'url', 'max:500'],
            'physical_location'    => ['nullable', 'string', 'max:500'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors'  => $validator->errors(),
        ], 422));
    }
}

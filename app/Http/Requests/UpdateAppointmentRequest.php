<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                => ['nullable', 'string', 'max:255'],
            'appointment_type'     => ['nullable', 'in:training,demo'],
            'location_type'        => ['nullable', 'in:physical,online,hybrid'],
            'client_id'            => ['nullable', 'uuid', 'exists:clients,id'],
            'trainer_id'           => ['nullable', 'uuid', 'exists:users,id'],
            'scheduled_date'       => ['nullable', 'date'],
            'scheduled_start_time' => ['nullable', 'date_format:H:i'],
            'scheduled_end_time'   => ['nullable', 'date_format:H:i'],
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

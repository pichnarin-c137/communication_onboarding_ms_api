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
            'title' => ['required_if:appointment_type,demo', 'nullable', 'string', 'max:255'],
            'appointment_type' => ['required', 'in:training,demo'],
            'location_type' => ['required', 'in:physical,online,hybrid'],
            // training always needs a client; a demo may target a client OR a CRM prospect.
            'client_id' => ['nullable', 'uuid', 'exists:clients,id', 'required_if:appointment_type,training'],
            'crm_contact_id' => ['nullable', 'uuid', 'exists:crm_contacts,id'],
            'crm_deal_id' => ['nullable', 'uuid', 'exists:crm_deals,id'],
            'trainer_id' => ['nullable', 'uuid', 'exists:users,id'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_start_time' => ['required', 'date_format:H:i'],
            'scheduled_end_time' => ['required', 'date_format:H:i', 'after:scheduled_start_time'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'physical_location' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('appointment_type');
            $hasClient = filled($this->input('client_id'));
            $hasContact = filled($this->input('crm_contact_id'));

            if ($type === 'training') {
                if ($hasContact || filled($this->input('crm_deal_id'))) {
                    $validator->errors()->add('crm_contact_id', 'Training appointments cannot be linked to a CRM contact or deal.');
                }

                return;
            }

            if ($type === 'demo') {
                // Exactly one target: an existing client or a CRM prospect.
                if ($hasClient === $hasContact) {
                    $validator->errors()->add('crm_contact_id', 'A demo must target exactly one of client_id or crm_contact_id.');
                }

                if (filled($this->input('crm_deal_id')) && ! $hasContact) {
                    $validator->errors()->add('crm_deal_id', 'crm_deal_id is only allowed when the demo targets a CRM contact.');
                }
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

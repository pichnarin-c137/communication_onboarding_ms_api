<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('userId');

        return [
            // User data (all optional for updates)
            'first_name' => ['sometimes', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'last_name' => ['sometimes', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'dob' => ['sometimes', 'date', 'before:today', 'after:1900-01-01'],
            'address' => ['sometimes', 'string', 'max:500'],
            'gender' => ['sometimes', 'in:male,female,other'],
            'nationality' => ['sometimes', 'string', 'max:100'],

            // Personal Information (images)
            'professtional_photo' => ['sometimes', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
            'nationality_card' => ['sometimes', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'family_book' => ['sometimes', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'birth_certificate' => ['sometimes', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'degreee_certificate' => ['sometimes', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'social_media' => ['sometimes', 'nullable', 'url', 'max:255'],

            // Emergency Contact
            'contact_first_name' => ['sometimes', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'contact_last_name' => ['sometimes', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'contact_relationship' => ['sometimes', 'string', 'in:Spouse,Parent,Sibling,Friend,Relative,Other'],
            'contact_phone_number' => ['sometimes', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'contact_address' => ['sometimes', 'string', 'max:500'],
            'contact_social_media' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'First name can only contain letters and spaces',
            'last_name.regex' => 'Last name can only contain letters and spaces',
            'contact_first_name.regex' => 'Contact first name can only contain letters and spaces',
            'contact_last_name.regex' => 'Contact last name can only contain letters and spaces',
            'contact_phone_number.regex' => 'Emergency contact phone number must be in valid international format',
            'professtional_photo.mimes' => 'Professional photo must be a JPEG, JPG, or PNG file',
            'nationality_card.mimes' => 'Nationality card must be a JPEG, PNG, or PDF file',
            'family_book.mimes' => 'Family book must be a JPEG, PNG, or PDF file',
            'birth_certificate.mimes' => 'Birth certificate must be a JPEG, PNG, or PDF file',
            'degreee_certificate.mimes' => 'Degree certificate must be a JPEG, PNG, or PDF file',
            '*.max' => 'The file size must not exceed 5MB',
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

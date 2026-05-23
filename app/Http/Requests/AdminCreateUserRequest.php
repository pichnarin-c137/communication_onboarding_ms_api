<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdminCreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional User data
            'first_name' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'last_name' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'dob' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'address' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', 'in:male,female,other'],
            'nationality' => ['nullable', 'string', 'max:100'],

            // Required
            'role' => ['required', 'string', 'in:admin,sale,trainer'],

            // Dedicated trainer roster — required when creating a sale user
            'trainer_ids' => [
                'required_if:role,sale',
                'array',
                'min:'.(int) config('coms.sale_roster.min_trainers', 1),
            ],
            'trainer_ids.*' => ['required', 'uuid', 'distinct', 'exists:users,id'],

            // Personal Information
            'professtional_photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
            'nationality_card' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'family_book' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'birth_certificate' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'degreee_certificate' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'social_media' => ['nullable', 'url', 'max:255'],

            // Emergency Contact
            'contact_first_name' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'contact_last_name' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'contact_relationship' => ['nullable', 'in:Spouse,Parent,Sibling,Friend,Relative,Other'],
            'contact_phone_number' => ['nullable', 'string', 'min:6', 'max:20', 'regex:/^[+0-9][0-9\s\-]*$/'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'contact_social_media' => ['nullable', 'url', 'max:255'],

            // Required Credential data
            'email' => ['required', 'email', 'max:255', 'unique:credentials,email'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9_]+$/',
                'unique:credentials,username',
            ],
            'phone_number' => [
                'required',
                'string',
                'min:6',
                'max:20',
                'regex:/^[+0-9][0-9\s\-]*$/',
                'unique:credentials,phone_number',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        $minTrainers = (int) config('coms.sale_roster.min_trainers', 1);

        return [
            'trainer_ids.required_if' => 'Sale users must be created with a dedicated trainer roster.',
            'trainer_ids.min' => "A sale user must have at least $minTrainers dedicated trainer(s).",
            'trainer_ids.*.uuid' => 'Each trainer id must be a valid UUID.',
            'trainer_ids.*.distinct' => 'Duplicate trainer ids are not allowed.',
            'trainer_ids.*.exists' => 'One or more trainer ids do not match an existing user.',
            'username.regex' => 'Username can only contain letters, numbers, and underscores',
            'phone_number.regex' => 'Phone number can only contain digits, spaces, dashes, and an optional leading +',
            'contact_phone_number.regex' => 'Emergency contact phone number can only contain digits, spaces, dashes, and an optional leading +',
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

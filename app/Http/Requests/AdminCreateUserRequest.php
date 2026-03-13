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
            // User data
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'dob' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'address' => ['required', 'string', 'max:500'],
            'gender' => ['required', 'in:male,female,other'],
            'nationality' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', 'in:admin,sale,trainer'],

            // Personal Information (images)
            'professtional_photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png', 'max:5120'], // 5MB max
            'nationality_card' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'family_book' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'birth_certificate' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'degreee_certificate' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'social_media' => ['nullable', 'url', 'max:255'],

            // Emergency Contact
            'contact_first_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'contact_last_name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s]+$/'],
            'contact_relationship' => ['required', 'string', 'in:Spouse,Parent,Sibling,Friend,Relative,Other'],
            'contact_phone_number' => ['required', 'string', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'contact_address' => ['required', 'string', 'max:500'],
            'contact_social_media' => ['nullable', 'url', 'max:255'],

            // Credential data
            'email' => ['required', 'email', 'max:255', 'unique:credentials,email'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:credentials,username'],
            'phone_number' => ['required', 'string', 'regex:/^\+?[1-9]\d{1,14}$/', 'unique:credentials,phone_number'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:100',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]+$/',
            ],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
            'username.regex' => 'Username can only contain letters, numbers, and underscores',
            'phone_number.regex' => 'Phone number must be in valid international format',
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

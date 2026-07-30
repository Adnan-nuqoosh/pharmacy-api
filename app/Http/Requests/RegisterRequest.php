<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    /**
     * Sign Up screen ke fields: Name, Email, Phone, Password
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:20', 'unique:users,phone', 'regex:/^\+?[0-9]{9,15}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // frontend se password_confirmation bhi bhejein
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex'        => 'Phone number sahi format mein likhein, e.g. +9715XXXXXXXX',
            'email.unique'       => 'Ye email pehle se registered hai.',
            'phone.unique'       => 'Ye phone number pehle se registered hai.',
            'password.confirmed' => 'Password aur confirmation match nahi karte.',
        ];
    }
}

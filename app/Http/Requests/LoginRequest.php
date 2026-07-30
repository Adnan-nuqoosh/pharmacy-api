<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    /**
     * Login screen: user email YA phone dono se login kar sakta hai.
     * Frontend ek hi field "login" bheje ga.
     */
    public function rules(): array
    {
        return [
            'login'    => ['required', 'string'],   // email ya phone
            'password' => ['required', 'string'],
        ];
    }
}

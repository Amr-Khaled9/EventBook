<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string|email|exists:users,email',
        ];
    }
    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.exists' => 'Email not found.',
        ];
    }
}

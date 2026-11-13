<?php

namespace App\Http\Requests;

use App\Enums\AuthProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TestLoginRequest extends FormRequest
{
    /**
     * Only the test provider signs in with a password.
     */
    public function authorize(): bool
    {
        return AuthProvider::current() === AuthProvider::Test;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}

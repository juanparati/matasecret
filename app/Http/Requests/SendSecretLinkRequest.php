<?php

namespace App\Http\Requests;

use App\Rules\AllowedEmailDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendSecretLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:128'],
            'recipients' => ['required', 'array', 'min:1', 'max:'.config('secrets.max_recipients')],
            'recipients.*' => ['required', 'string', 'email', 'distinct:ignore_case', new AllowedEmailDomain],
            'message' => ['nullable', 'string', 'max:'.config('secrets.max_message_length')],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipients.required' => 'Add at least one recipient.',
            'recipients.max' => 'You can send the link to at most :max recipients at once.',
            'recipients.*.email' => ':input is not a valid email address.',
            'recipients.*.distinct' => ':input was added more than once.',
        ];
    }

    /**
     * @return list<string>
     */
    public function recipients(): array
    {
        return array_values(array_map(
            fn (mixed $email): string => strtolower((string) $email),
            (array) $this->input('recipients'),
        ));
    }
}

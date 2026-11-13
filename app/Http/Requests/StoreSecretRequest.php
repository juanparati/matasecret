<?php

namespace App\Http\Requests;

use App\Enums\SecretExpiration;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecretRequest extends FormRequest
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
        $minimumSeconds = (int) config('secrets.min_custom_expiration_seconds');

        return [
            'content' => [
                'required',
                'string',
                'max:'.config('secrets.max_length'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && trim($value) === '') {
                        $fail('The secret cannot be empty.');
                    }
                },
            ],
            'expiration' => ['required', Rule::enum(SecretExpiration::class)],
            'expires_at' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('expiration') === SecretExpiration::Custom->value),
                'date',
                'after_or_equal:'.CarbonImmutable::now()->addSeconds($minimumSeconds)->toIso8601String(),
                'before_or_equal:'.CarbonImmutable::now()->addDays((int) config('secrets.max_expiration_days'))->toIso8601String(),
            ],
            'self_destruct' => ['required', 'boolean'],
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
            'content.required' => 'The secret cannot be empty.',
            'content.max' => 'The secret may not be longer than :max characters.',
            'expires_at.required' => 'Choose when the secret should expire.',
            'expires_at.after_or_equal' => 'The expiration must be at least one minute in the future.',
            'expires_at.before_or_equal' => 'The expiration cannot be more than '.config('secrets.max_expiration_days').' days in the future.',
        ];
    }

    /**
     * The moment the secret expires, in UTC.
     */
    public function expiresAt(): CarbonImmutable
    {
        $expiration = $this->enum('expiration', SecretExpiration::class);

        return $expiration === SecretExpiration::Custom
            ? CarbonImmutable::parse($this->string('expires_at')->toString())->utc()
            : CarbonImmutable::now()->utc()->addSeconds((int) $expiration?->seconds());
    }
}

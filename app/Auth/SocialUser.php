<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LogicException;

/**
 * A user who signed in with the configured provider, resolved from the session. There is
 * no user table.
 *
 * @implements Arrayable<string, string|null>
 */
final readonly class SocialUser implements Arrayable, Authenticatable, JsonSerializable
{
    public const string SESSION_KEY = 'auth_user';

    public function __construct(
        public string $email,
        public string $name,
        public ?string $avatar = null,
    ) {}

    /**
     * Rebuild the user from the data stored in the session.
     */
    public static function fromSession(mixed $attributes): ?self
    {
        if (! is_array($attributes) || ! is_string($attributes['email'] ?? null)) {
            return null;
        }

        return new self(
            email: $attributes['email'],
            name: is_string($attributes['name'] ?? null) ? $attributes['name'] : $attributes['email'],
            avatar: is_string($attributes['avatar'] ?? null) ? $attributes['avatar'] : null,
        );
    }

    /**
     * @return array{email: string, name: string, avatar: string|null}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            'avatar' => $this->avatar,
        ];
    }

    /**
     * @return array{email: string, name: string, avatar: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getAuthIdentifierName(): string
    {
        return 'email';
    }

    public function getAuthIdentifier(): string
    {
        return $this->email;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        throw new LogicException('Signed-in users do not have a password.');
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * @param  string  $value
     */
    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}

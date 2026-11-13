<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialiteAuthControllerTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('secrets.create'))->assertRedirect(route('login'));
    }

    public function test_login_page_renders_the_provider_and_allowed_domains(): void
    {
        config(['auth.social_provider' => 'okta', 'secrets.allowed_domains' => ['example.com', 'Example.org']]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('provider', 'Okta')
                ->where('testAccount', false)
                ->where('domains', ['example.com', 'example.org'])
                ->where('intendedPath', null));
    }

    public function test_login_page_exposes_the_intended_secret_path(): void
    {
        $this->get('/s/AAAAAAAAAAAAAAAAAAAAAA')->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertInertia(fn ($page) => $page->where('intendedPath', '/s/AAAAAAAAAAAAAAAAAAAAAA'));
    }

    public function test_redirect_sends_the_user_to_google_with_the_hosted_domain_hint(): void
    {
        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'secret']);

        $response = $this->get(route('auth.redirect'));

        $response->assertRedirectContains('accounts.google.com');
        $this->assertStringContainsString('hd=example.com', (string) $response->headers->get('Location'));
    }

    public function test_redirect_hints_any_hosted_domain_when_several_domains_are_allowed(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'secret',
            'secrets.allowed_domains' => ['example.com', 'example.org'],
        ]);

        $response = $this->get(route('auth.redirect'));

        $this->assertStringContainsString('hd=%2A', (string) $response->headers->get('Location'));
    }

    public function test_verified_workspace_account_signs_in_and_returns_to_the_intended_url(): void
    {
        Socialite::fake('google', $this->account());

        $this->withSession(['url.intended' => url('/s/AAAAAAAAAAAAAAAAAAAAAA')])
            ->get(route('auth.callback'))
            ->assertRedirect(url('/s/AAAAAAAAAAAAAAAAAAAAAA'))
            ->assertSessionHas('auth_user', [
                'email' => 'bob@example.com',
                'name' => 'Bob',
                'avatar' => 'https://example.com/bob.png',
            ]);

        $this->get(route('secrets.create'))->assertOk();
    }

    public function test_workspace_account_from_any_allowed_domain_signs_in(): void
    {
        config(['secrets.allowed_domains' => ['example.com', 'example.org']]);
        Socialite::fake('google', $this->account(['email' => 'Bob@Example.org', 'hd' => 'example.org']));

        $this->get(route('auth.callback'))
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHas('auth_user.email', 'bob@example.org');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('rejectedWorkspaceAccounts')]
    public function test_callback_rejects_accounts_outside_the_workspace(array $overrides): void
    {
        Socialite::fake('google', $this->account($overrides));

        $this->get(route('auth.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Only verified @example.com accounts can sign in.')
            ->assertSessionMissing('auth_user');
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedWorkspaceAccounts(): array
    {
        return [
            'another domain' => [['email' => 'mallory@evil.com', 'hd' => 'evil.com']],
            'personal gmail account' => [['email' => 'bob@gmail.com', 'hd' => null]],
            'workspace email without hd claim' => [['hd' => null]],
            'hd claim from another workspace' => [['hd' => 'evil.com']],
            'unverified email' => [['email_verified' => false]],
            'no email' => [['email' => null]],
        ];
    }

    public function test_rejection_names_every_allowed_domain(): void
    {
        config(['secrets.allowed_domains' => ['example.com', 'example.org', 'example.net']]);
        Socialite::fake('google', $this->account(['email' => 'mallory@evil.com']));

        $this->get(route('auth.callback'))
            ->assertSessionHas('error', 'Only verified @example.com, @example.org or @example.net accounts can sign in.');
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    #[DataProvider('verifiedAccounts')]
    public function test_callback_signs_in_when_the_provider_vouches_for_the_email(string $provider, array $raw): void
    {
        config(['auth.social_provider' => $provider]);
        Socialite::fake($provider, $this->providerAccount($raw));

        $this->get(route('auth.callback'))
            ->assertRedirect(route('secrets.create'))
            ->assertSessionHas('auth_user.email', 'bob@example.com');
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function verifiedAccounts(): array
    {
        return [
            'apple sends the claim as a string' => ['apple', ['email_verified' => 'true']],
            'okta sends the claim as a boolean' => ['okta', ['email_verified' => true]],
            'zoom names the claim verified' => ['zoom', ['verified' => 1]],
            'openid connect without the claim' => ['openidconnect', []],
            'github only returns verified emails' => ['github', []],
            'amazon has no claim to check' => ['amazon', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    #[DataProvider('unverifiedAccounts')]
    public function test_callback_rejects_emails_the_provider_does_not_verify(string $provider, array $raw): void
    {
        config(['auth.social_provider' => $provider]);
        Socialite::fake($provider, $this->providerAccount($raw));

        $this->get(route('auth.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('auth_user');
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function unverifiedAccounts(): array
    {
        return [
            'apple says the email is not verified' => ['apple', ['email_verified' => 'false']],
            'okta omits the claim' => ['okta', []],
            'zoom says the email is not verified' => ['zoom', ['verified' => 0]],
            'openid connect says the email is not verified' => ['openidconnect', ['email_verified' => false]],
        ];
    }

    public function test_failed_provider_callback_returns_to_login_with_an_error(): void
    {
        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'secret']);

        $this->get(route('auth.callback', ['code' => 'abc', 'state' => 'forged']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google Workspace sign-in failed. Please try again.')
            ->assertSessionMissing('auth_user');
    }

    public function test_form_post_callback_bounces_to_the_get_callback_without_starting_a_session(): void
    {
        $response = $this->post(route('auth.callback.form-post'), [
            'code' => 'abc',
            'state' => 'xyz',
            'user' => '{"name":{"firstName":"Bob"}}',
            'unexpected' => 'dropped',
        ]);

        $response->assertStatus(303)
            ->assertRedirect(route('auth.callback', ['code' => 'abc', 'state' => 'xyz', 'user' => '{"name":{"firstName":"Bob"}}']))
            ->assertCookieMissing((string) config('session.cookie'));
    }

    public function test_login_page_offers_the_test_account_form(): void
    {
        config(['auth.social_provider' => 'test']);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('provider', 'Test')
                ->where('testAccount', true));
    }

    public function test_test_account_signs_in_from_outside_the_allowed_domains_and_returns_to_the_intended_url(): void
    {
        config(['auth.social_provider' => 'test']);

        $this->withSession(['url.intended' => url('/s/AAAAAAAAAAAAAAAAAAAAAA')])
            ->post(route('auth.test'), ['email' => 'Secret@Example.net', 'password' => 'secret'])
            ->assertRedirect(url('/s/AAAAAAAAAAAAAAAAAAAAAA'))
            ->assertSessionHas('auth_user', [
                'email' => 'secret@example.net',
                'name' => 'Test user',
                'avatar' => null,
            ]);

        $this->get(route('secrets.create'))->assertOk();
    }

    #[DataProvider('wrongTestCredentials')]
    public function test_test_account_rejects_wrong_credentials(string $email, string $password): void
    {
        config(['auth.social_provider' => 'test']);

        $this->from(route('login'))
            ->post(route('auth.test'), ['email' => $email, 'password' => $password])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'These credentials do not match the test account.'])
            ->assertSessionMissing('auth_user');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function wrongTestCredentials(): array
    {
        return [
            'wrong password' => ['secret@example.net', 'wrong'],
            'wrong email' => ['bob@example.net', 'secret'],
        ];
    }

    public function test_test_login_requires_an_email_and_a_password(): void
    {
        config(['auth.social_provider' => 'test']);

        $this->post(route('auth.test'))
            ->assertSessionHasErrors([
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
            ])
            ->assertSessionMissing('auth_user');
    }

    public function test_test_login_is_forbidden_when_a_real_provider_is_configured(): void
    {
        $this->post(route('auth.test'), ['email' => 'secret@example.net', 'password' => 'secret'])
            ->assertForbidden()
            ->assertSessionMissing('auth_user');
    }

    public function test_test_provider_fails_in_production_instead_of_signing_in(): void
    {
        config(['auth.social_provider' => 'test']);
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('auth.test'), ['email' => 'secret@example.net', 'password' => 'secret'])
            ->assertInternalServerError()
            ->assertSessionMissing('auth_user');
    }

    public function test_redirect_returns_to_login_with_the_test_provider(): void
    {
        config(['auth.social_provider' => 'test']);

        $this->get(route('auth.redirect'))->assertRedirect(route('login'));
    }

    public function test_logout_signs_the_user_out(): void
    {
        $this->signIn()
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->get(route('secrets.create'))->assertRedirect(route('login'));
    }

    /**
     * An account from a provider other than Google, with only the given claims.
     *
     * @param  array<string, mixed>  $claims
     */
    private function providerAccount(array $claims): SocialiteUser
    {
        return SocialiteUser::fake([
            'email' => 'bob@example.com',
            'name' => 'Bob',
            ...$claims,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function account(array $overrides = []): SocialiteUser
    {
        return SocialiteUser::fake([
            'email' => 'bob@example.com',
            'name' => 'Bob',
            'avatar' => 'https://example.com/bob.png',
            'hd' => 'example.com',
            'email_verified' => true,
            ...$overrides,
        ]);
    }
}

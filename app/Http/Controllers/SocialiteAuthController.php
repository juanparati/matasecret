<?php

namespace App\Http\Controllers;

use App\Auth\SocialUser;
use App\Enums\AuthProvider;
use App\Enums\EmailVerification;
use App\Http\Requests\TestLoginRequest;
use App\Rules\AllowedEmailDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class SocialiteAuthController extends Controller
{
    /**
     * Show the sign-in page.
     */
    public function login(Request $request): Response
    {
        $provider = AuthProvider::current();

        return Inertia::render('Auth/Login', [
            'provider' => $provider->label(),
            'testAccount' => $provider === AuthProvider::Test,
            'domains' => AllowedEmailDomain::domains(),
            'error' => $request->session()->get('error'),
            'intendedPath' => $this->intendedPath($request),
        ]);
    }

    /**
     * Send the user to the configured sign-in provider.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        $provider = AuthProvider::current();

        if ($provider === AuthProvider::Test) {
            return to_route('login');
        }

        $driver = Socialite::driver($provider->value);

        if ($driver instanceof AbstractProvider) {
            $driver->with($provider->redirectParameters(AllowedEmailDomain::domains()));
        }

        return $driver->redirect();
    }

    /**
     * Handle the provider callback, accepting only verified accounts from an allowed domain.
     */
    public function callback(Request $request): RedirectResponse
    {
        $provider = AuthProvider::current();

        try {
            $account = Socialite::driver($provider->value)->user();
        } catch (Throwable) {
            return to_route('login')->with('error', $provider->label().' sign-in failed. Please try again.');
        }

        $email = Str::lower((string) $account->getEmail());

        if (! $account instanceof AbstractUser || ! $this->isAllowed($provider, $account, $email)) {
            Log::warning('auth.rejected', ['actor' => $email]);

            return to_route('login')->with('error', 'Only verified '.AllowedEmailDomain::describe().' accounts can sign in.');
        }

        return $this->signIn($request, new SocialUser(
            email: $email,
            name: $account->getName() ?: $email,
            avatar: $account->getAvatar(),
        ));
    }

    /**
     * Sign in with the test account. The domain check is skipped: the account is not a
     * real identity, and the test provider never runs in production.
     */
    public function testLogin(TestLoginRequest $request): RedirectResponse
    {
        $email = Str::lower($request->string('email')->toString());

        if ($email !== Str::lower((string) config('auth.test_account.email'))
            || ! hash_equals((string) config('auth.test_account.password'), $request->string('password')->toString())) {
            Log::warning('auth.rejected', ['actor' => $email]);

            throw ValidationException::withMessages(['email' => 'These credentials do not match the test account.']);
        }

        return $this->signIn($request, new SocialUser(email: $email, name: 'Test user'));
    }

    /**
     * Apple posts its callback from another site, so the browser leaves the session cookie
     * out. Bounce it to the GET callback, which the browser sends with the cookie.
     */
    public function formPostCallback(Request $request): RedirectResponse
    {
        return redirect()->to(route('auth.callback', $request->only(['code', 'state', 'user', 'error'])), 303);
    }

    /**
     * Sign the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::forgetGuards();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }

    /**
     * Start a fresh session for the user and send them where they were heading.
     */
    private function signIn(Request $request, SocialUser $user): RedirectResponse
    {
        $request->session()->regenerate();
        $request->session()->put(SocialUser::SESSION_KEY, $user->toArray());

        Log::info('auth.login', ['actor' => $user->email]);

        return redirect()->intended(route('secrets.create'));
    }

    /**
     * The path the user was heading to before being asked to sign in.
     */
    private function intendedPath(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');

        return is_string($intended) ? (parse_url($intended, PHP_URL_PATH) ?: null) : null;
    }

    /**
     * The email must belong to an allowed domain and be verified as far as the provider can
     * tell. Google must also confirm the account belongs to an allowed Workspace domain.
     */
    private function isAllowed(AuthProvider $provider, AbstractUser $account, string $email): bool
    {
        $raw = $account->getRaw();

        if (! AllowedEmailDomain::isAllowed($email)) {
            return false;
        }

        if ($provider === AuthProvider::GoogleWorkspace
            && ! in_array(Str::lower((string) ($raw['hd'] ?? '')), AllowedEmailDomain::domains(), true)) {
            return false;
        }

        $claim = (string) $provider->verifiedEmailClaim();

        return match ($provider->emailVerification()) {
            EmailVerification::Claim => $this->isTrue($raw[$claim] ?? null),
            EmailVerification::ClaimIfPresent => ($raw[$claim] ?? null) === null || $this->isTrue($raw[$claim]),
            EmailVerification::Guaranteed, EmailVerification::Unverified => true,
        };
    }

    /**
     * Providers send the verified flag as a boolean, 1, or the string "true" (Apple).
     */
    private function isTrue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
    }
}

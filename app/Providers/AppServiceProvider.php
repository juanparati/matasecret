<?php

namespace App\Providers;

use App\Auth\SocialUser;
use App\Enums\AuthProvider;
use App\Secrets\ApcuSecretStore;
use App\Secrets\ArraySecretStore;
use App\Secrets\RedisSecretStore;
use App\Secrets\SecretCipher;
use App\Secrets\SecretStore;
use App\Secrets\SecretVault;
use App\Secrets\ShmopSecretStore;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SecretStore::class, fn (Application $app): SecretStore => match ($store = config('secrets.store')) {
            'apcu' => new ApcuSecretStore,
            'shmop' => new ShmopSecretStore(
                key: (int) config('secrets.shmop.key'),
                slots: (int) config('secrets.shmop.slots'),
                slotSize: (int) config('secrets.shmop.slot_size'),
                lockPath: (string) config('secrets.shmop.lock_path'),
                signingKey: $app->make(Encrypter::class)->getKey(),
            ),
            'redis' => new RedisSecretStore($app->make('redis')->connection(config('secrets.redis.connection'))),
            'array' => new ArraySecretStore,
            default => throw new InvalidArgumentException("Unsupported secret store [{$store}]."),
        });

        $this->app->singleton(SecretVault::class, fn (Application $app): SecretVault => new SecretVault(
            store: $app->make(SecretStore::class),
            cipher: $app->make(SecretCipher::class),
            encrypter: $app->make(Encrypter::class),
            maxStored: (int) config('secrets.max_stored'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureTrustedProxies();
        $this->configureAuthentication();
        $this->configureSocialiteProvider();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Trust the reverse proxy in front of the application, when there is one.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = trim((string) config('app.trusted_proxies'));

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map(trim(...), explode(',', $proxies)));
        }
    }

    /**
     * Resolve the signed-in user from the session.
     */
    protected function configureAuthentication(): void
    {
        Auth::viaRequest('social-session', fn (Request $request): ?SocialUser => $request->hasSession()
            ? SocialUser::fromSession($request->session()->get(SocialUser::SESSION_KEY))
            : null,
        );
    }

    /**
     * Register the SocialiteProviders driver of the configured provider. The listener is
     * only added once the package is installed: a listener without its package would
     * break every Socialite driver.
     */
    protected function configureSocialiteProvider(): void
    {
        $provider = AuthProvider::tryFrom((string) config('auth.social_provider'));
        $providerClass = $provider?->providerClass();

        if ($provider === null || $providerClass === null || ! class_exists($providerClass)) {
            return;
        }

        Event::listen('SocialiteProviders\\Manager\\SocialiteWasCalled', function (object $event) use ($provider, $providerClass): void {
            if (method_exists($event, 'extendSocialite')) {
                $event->extendSocialite($provider->value, $providerClass, $provider->oauthOneServerClass());
            }
        });
    }

    /**
     * Configure the per-user rate limiters for secrets.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('secrets.create', fn (Request $request): Limit => Limit::perHour(
            (int) config('secrets.rate_limits.create_per_hour'),
        )->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('secrets.reveal', fn (Request $request): Limit => Limit::perMinute(
            (int) config('secrets.rate_limits.reveal_per_minute'),
        )->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}

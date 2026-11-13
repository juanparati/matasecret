<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * A fresh CSP nonce is generated for every request. This must happen
     * unconditionally: under Octane the Vite instance is a singleton and is
     * not reset between requests, so a guarded call would reuse the nonce.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Build the Content-Security-Policy header value.
     */
    private function contentSecurityPolicy(string $nonce): string
    {
        $devServer = $this->viteDevServerOrigins();

        /** @var array<string, list<string>> $directives */
        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$devServer['http']],
            'style-src' => ["'self'", "'nonce-{$nonce}'", ...$devServer['http']],
            'img-src' => ["'self'", 'data:', 'https://*.googleusercontent.com', ...$devServer['http']],
            'font-src' => ["'self'", ...$devServer['http']],
            'connect-src' => ["'self'", ...$devServer['http'], ...$devServer['ws']],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        if (app()->isProduction()) {
            $directives['upgrade-insecure-requests'] = [];
        }

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => trim($directive.' '.implode(' ', $sources)))
            ->implode('; ');
    }

    /**
     * Origins of the Vite dev server while `npm run dev` is running, so that
     * hot module replacement (scripts, injected styles and the HMR websocket)
     * keeps working under the policy. Empty in production.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private function viteDevServerOrigins(): array
    {
        if (! Vite::isRunningHot()) {
            return ['http' => [], 'ws' => []];
        }

        $url = rtrim((string) file_get_contents(Vite::hotFile()));

        if ($url === '') {
            return ['http' => [], 'ws' => []];
        }

        return [
            'http' => [$url],
            'ws' => [preg_replace('/^http/', 'ws', $url)],
        ];
    }
}

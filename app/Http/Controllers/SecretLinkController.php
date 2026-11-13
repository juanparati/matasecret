<?php

namespace App\Http\Controllers;

use App\Auth\SocialUser;
use App\Http\Requests\SendSecretLinkRequest;
use App\Mail\SecretLinkMail;
use App\Secrets\SecretVault;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SecretLinkController extends Controller implements HasMiddleware
{
    public function __construct(private SecretVault $vault) {}

    /**
     * Email links do not exist when the installation has no mailer.
     *
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next): mixed {
                abort_unless((bool) config('secrets.email_links'), 404);

                return $next($request);
            }),
        ];
    }

    /**
     * Email the secret link to members of the organization.
     */
    public function store(SendSecretLinkRequest $request, string $id): JsonResponse
    {
        /** @var SocialUser $sender */
        $sender = $request->user();
        $key = $request->string('key')->toString();
        $secret = $this->vault->findWithKey($id, $key);

        if ($secret === null) {
            $this->vault->audit('secret.rejected', $id, $sender->email, ['reason' => 'email_unavailable']);

            return response()->json([
                'message' => 'This secret does not exist or is no longer available.',
            ], 404);
        }

        $recipients = $request->recipients();
        $rateLimitKey = 'secrets.email:'.$sender->email;
        $maxPerHour = (int) config('secrets.rate_limits.email_recipients_per_hour');

        if (RateLimiter::attempts($rateLimitKey) + count($recipients) > $maxPerHour) {
            return response()->json([
                'message' => 'You can email at most '.$maxPerHour.' recipients per hour. Try again in '.ceil(RateLimiter::availableIn($rateLimitKey) / 60).' minutes.',
            ], 429);
        }

        $mail = new SecretLinkMail(
            sender: $sender,
            link: route('secrets.show', $id).'#'.$key,
            expiresAt: $secret->expiresAt,
            selfDestruct: $secret->selfDestruct,
            personalMessage: $request->filled('message') ? $request->string('message')->toString() : null,
        );

        $sent = [];

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send($mail);
            } catch (Throwable $exception) {
                Log::error('secret.email_failed', [
                    'secret' => hash('sha256', $id),
                    'actor' => $sender->email,
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'The email could not be sent to '.$recipient.'. Please try again later.',
                    'sent' => $sent,
                ], 502);
            } finally {
                RateLimiter::hit($rateLimitKey, 3600);
            }

            $sent[] = $recipient;
        }

        $this->vault->audit('secret.emailed', $id, $sender->email, ['recipients' => $sent]);

        return response()->json(['sent' => $sent]);
    }
}

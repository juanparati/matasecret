<?php

namespace App\Http\Controllers;

use App\Auth\SocialUser;
use App\Enums\SecretExpiration;
use App\Http\Requests\DestroySecretRequest;
use App\Http\Requests\RevealSecretRequest;
use App\Http\Requests\StoreSecretRequest;
use App\Rules\AllowedEmailDomain;
use App\Secrets\SecretVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecretController extends Controller
{
    public function __construct(private SecretVault $vault) {}

    /**
     * Show the form to create a secret.
     */
    public function create(): Response
    {
        return Inertia::render('Secrets/Create', [
            'expirationOptions' => SecretExpiration::options(),
            'defaultExpiration' => SecretExpiration::default()->value,
            'maxLength' => (int) config('secrets.max_length'),
            'minCustomExpirationSeconds' => (int) config('secrets.min_custom_expiration_seconds'),
            'maxExpirationDays' => (int) config('secrets.max_expiration_days'),
            'maxRecipients' => (int) config('secrets.max_recipients'),
            'maxMessageLength' => (int) config('secrets.max_message_length'),
            'domains' => AllowedEmailDomain::domains(),
            'emailLinks' => (bool) config('secrets.email_links'),
        ]);
    }

    /**
     * Store a new secret. The key is returned once and never kept on the server.
     */
    public function store(StoreSecretRequest $request): JsonResponse
    {
        $secret = $this->vault->create(
            content: $request->string('content')->toString(),
            expiresAt: $request->expiresAt(),
            selfDestruct: $request->boolean('self_destruct'),
            creatorEmail: $this->user($request)->email,
        );

        return response()->json([
            'id' => $secret->id,
            'key' => $secret->key,
            'url' => route('secrets.show', $secret->id),
            'expiresAt' => $secret->expiresAt->toIso8601String(),
            'selfDestruct' => $secret->selfDestruct,
        ], 201);
    }

    /**
     * Show the reveal page. Nothing is decrypted until the viewer asks for it.
     */
    public function show(string $id): Response
    {
        $secret = $this->vault->find($id);

        return Inertia::render('Secrets/Reveal', [
            'id' => $id,
            'secret' => $secret === null ? null : [
                'sharedBy' => $this->vault->creatorEmail($secret),
                'expiresAt' => $secret->expiresAt->toIso8601String(),
                'selfDestruct' => $secret->selfDestruct,
            ],
            'visibleSeconds' => (int) config('secrets.reveal_visible_seconds'),
        ]);
    }

    /**
     * Decrypt a secret for the viewer.
     */
    public function reveal(RevealSecretRequest $request, string $id): JsonResponse
    {
        $content = $this->vault->reveal($id, $request->string('key')->toString(), $this->user($request)->email);

        if ($content === null) {
            return $this->unavailable();
        }

        return response()->json(['content' => $content])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Destroy a secret before it expires.
     */
    public function destroy(DestroySecretRequest $request, string $id): JsonResponse
    {
        if (! $this->vault->destroy($id, $request->string('key')->toString(), $this->user($request)->email)) {
            return $this->unavailable();
        }

        return response()->json(status: 204);
    }

    private function user(Request $request): SocialUser
    {
        /** @var SocialUser */
        return $request->user();
    }

    /**
     * One response for every failure, so callers cannot tell why a secret is unavailable.
     */
    private function unavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'This secret does not exist or is no longer available.',
        ], 404);
    }
}

<x-mail::message>
# {{ $sender->name }} shared a secret with you

{{ $sender->name }} ({{ $sender->email }}) used {{ config('app.name') }} to share a secret with you.

@if ($personalMessage)
<x-mail::panel>
{{ $personalMessage }}
</x-mail::panel>
@endif

<x-mail::button :url="$link">
View secret
</x-mail::button>

The link expires on **{{ $expiresAt->utc()->format('D, j M Y H:i') }} UTC**.
@if ($selfDestruct)
It can only be viewed **once**: the secret is destroyed as soon as it is revealed.
@endif

You will be asked to sign in with your {{ $provider }} account. Do not forward this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

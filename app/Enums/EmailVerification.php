<?php

namespace App\Enums;

/**
 * How a sign-in provider proves that the email address it returns belongs to the user.
 */
enum EmailVerification
{
    /** The provider sends a verification claim that must be true. */
    case Claim;

    /** The verification claim is checked only when the provider sends it. */
    case ClaimIfPresent;

    /** The provider only ever returns verified addresses. */
    case Guaranteed;

    /** Nothing proves the address, so the domain check trusts the provider. */
    case Unverified;
}

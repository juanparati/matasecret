<?php

namespace App\Secrets;

use Carbon\CarbonImmutable;

/**
 * The result of creating a secret. The key only exists here and in the link.
 */
final readonly class CreatedSecret
{
    public function __construct(
        public string $id,
        public string $key,
        public CarbonImmutable $expiresAt,
        public bool $selfDestruct,
    ) {}
}

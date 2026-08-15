<?php

declare(strict_types=1);

namespace FantasyPros\Data\Api;

use FantasyPros\Data\Contracts\ApiDataContract;
use FantasyPros\Data\Infrastructure\Payload;

/**
 * The quota envelope every response carries.
 *
 * Worth surfacing rather than discarding: on the free tier the players endpoint
 * silently truncates to `limit` results (10 at the time of writing) while still
 * reporting the full `count`, so a caller that ignores this will quietly
 * believe it has a complete roster.
 */
final readonly class ApiLimits implements ApiDataContract
{
    public function __construct(
        public bool $limited,
        public ?string $tier,
        public ?int $limit,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            limited: $payload->bool('public_api_limited'),
            tier: $payload->nullableString('tier'),
            limit: $payload->nullableInt('limit'),
        );
    }
}

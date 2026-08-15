<?php

declare(strict_types=1);

namespace FantasyPros\Data\Envelopes;

use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\PlayerPoints;
use FantasyPros\Data\Infrastructure\Payload;
use Saloon\Http\Response;

/**
 * The player-points endpoint envelope.
 *
 * Thinner than the other envelopes because the route says less about itself:
 * there is no `count` to compare against and no `week`, since the response
 * spans the whole requested range rather than a single week.
 */
final readonly class PlayerPointsCollection
{
    /**
     * @param  list<PlayerPoints>  $players  ordered by the API, highest average first
     */
    public function __construct(
        public int $season,
        public ?string $scoring,
        public array $players,
        public ApiLimits $limits,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $payload = Payload::fromResponse($response);

        return new self(
            season: $payload->int('season'),
            scoring: $payload->nullableString('scoring'),
            players: $payload->has('players')
                ? array_map(PlayerPoints::fromPayload(...), $payload->objects('players'))
                : [],
            limits: ApiLimits::fromPayload($payload),
        );
    }
}

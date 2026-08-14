<?php

declare(strict_types=1);

namespace FantasyPros\Data;

use FantasyPros\Enums\Sport;
use Saloon\Http\Response;

/**
 * The players endpoint envelope.
 */
final readonly class PlayerCollection
{
    /**
     * @param  list<NflPlayer>  $players
     */
    public function __construct(
        public Sport $sport,
        public int $count,
        public int $season,
        public int $week,
        public array $players,
        public ApiLimits $limits,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $payload = Payload::fromResponse($response);

        return new self(
            sport: Sport::from($payload->string('sport')),
            count: $payload->int('count'),
            season: $payload->int('season'),
            week: $payload->int('week'),
            players: array_map(
                NflPlayer::fromPayload(...),
                $payload->objects('players'),
            ),
            limits: ApiLimits::fromPayload($payload),
        );
    }

    /**
     * True when the tier's cap held back players the count says exist.
     */
    public function truncated(): bool
    {
        return count($this->players) < $this->count;
    }
}

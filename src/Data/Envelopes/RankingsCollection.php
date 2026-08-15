<?php

declare(strict_types=1);

namespace FantasyPros\Data\Envelopes;

use FantasyPros\Data\Api\RankedPlayer;
use FantasyPros\Data\Infrastructure\ApiLimits;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\Sport;
use Saloon\Http\Response;

/**
 * The rankings endpoint envelope.
 *
 * The payload's `experts` and `ecr_experts` maps (counts and ID lists per
 * scoring and position) are deliberately not mapped -- nothing reads them yet,
 * and they are three levels of nesting deep.
 */
final readonly class RankingsCollection
{
    /**
     * @param  list<RankedPlayer>  $players
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
                RankedPlayer::fromPayload(...),
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

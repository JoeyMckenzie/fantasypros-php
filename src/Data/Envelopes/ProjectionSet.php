<?php

declare(strict_types=1);

namespace FantasyPros\Data\Envelopes;

use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\ProjectedPlayer;
use FantasyPros\Data\Infrastructure\Payload;
use Saloon\Http\Response;

/**
 * The projections endpoint envelope.
 */
final readonly class ProjectionSet
{
    /**
     * @param  int  $week  the week projected. Zero means preseason, and is also
     *                     what a rest-of-season request echoes back.
     * @param  string  $positions  the position filter the API applied, echoed back
     * @param  bool  $restOfSeason  whether this is a rest-of-season set; present on
     *                              the wire only when `ros=true` was sent
     * @param  list<ProjectedPlayer>  $players
     */
    public function __construct(
        public int $season,
        public int $week,
        public int $count,
        public string $positions,
        public ?string $scoring,
        public bool $restOfSeason,
        public array $players,
        public ApiLimits $limits,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $payload = Payload::fromResponse($response);

        return new self(
            season: $payload->int('season'),
            week: $payload->int('week'),
            count: $payload->int('count'),
            positions: $payload->string('positions'),
            scoring: $payload->nullableString('scoring'),
            restOfSeason: $payload->bool('ros_projections'),
            // A rest-of-season request answers with a literal `null` here rather
            // than an empty array, and `objects()` rejects a non-array. `has()`
            // is isset()-based, so null and absent both land on the empty list.
            players: $payload->has('players')
                ? array_map(ProjectedPlayer::fromPayload(...), $payload->objects('players'))
                : [],
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

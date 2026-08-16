<?php

declare(strict_types=1);

namespace FantasyPros\Requests;

use FantasyPros\Data\Envelopes\PlayerPointsCollection;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/{season}/player-points: points actually scored, week by week.
 *
 * The counterpart to projections: this is what happened, not what was expected.
 */
final class GetPlayerPointsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  ?int  $startWeek  the first week to count from; the API defaults to 1
     * @param  ?int  $endWeek  the last week to count through; the API defaults to the
     *                         final week of the regular season
     * @param  ?NflPosition  $position  optional here, unlike on projections
     * @param  bool  $minimal  return less information per player, for a smaller response.
     *                         Drops the name, position and team, leaving the ID and the points.
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly int $season,
        private readonly ?int $startWeek = null,
        private readonly ?int $endWeek = null,
        private readonly ?NflPosition $position = null,
        private readonly ?NflScoringType $scoring = null,
        private readonly bool $minimal = false,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/%d/player-points', $this->sport->pathSegment(), $this->season);
    }

    #[Override]
    public function createDtoFromResponse(Response $response): PlayerPointsCollection
    {
        return PlayerPointsCollection::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'start' => $this->startWeek,
            'end' => $this->endWeek,
            'position' => $this->position?->value,
            'scoring' => $this->scoring?->value,
            'min' => $this->minimal ? 'true' : null,
        ];

        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

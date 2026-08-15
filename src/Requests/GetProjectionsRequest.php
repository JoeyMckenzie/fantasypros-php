<?php

declare(strict_types=1);

namespace FantasyPros\Requests;

use FantasyPros\Data\Envelopes\ProjectionSet;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/{season}/projections -- projected stat lines for a position.
 *
 * The spec also documents a `filters` parameter here, described as "a comma
 * delimited string of expert IDs". Projections have no experts -- the text is
 * carried over from the ranking routes -- so it is deliberately not exposed.
 */
final class GetProjectionsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  NflPosition  $position  required by the endpoint, as on consensus-rankings
     * @param  list<NflPosition>  $positions  narrow further to these positions
     * @param  list<int>  $playerIds  FantasyPros player IDs to restrict the set to
     * @param  bool  $restOfSeason  ask for rest-of-season rather than weekly projections.
     *                              Note the API answers this with an empty set once a season
     *                              has no games left, and does so without erroring.
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly int $season,
        private readonly NflPosition $position,
        private readonly ?int $week = null,
        private readonly bool $restOfSeason = false,
        private readonly array $positions = [],
        private readonly array $playerIds = [],
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/%d/projections', $this->sport->pathSegment(), $this->season);
    }

    #[Override]
    public function createDtoFromResponse(Response $response): ProjectionSet
    {
        return ProjectionSet::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'position' => $this->position->value,
            // Week 0 is meaningful -- it asks for preseason projections -- so it
            // has to survive the filter below.
            'week' => $this->week,
            'ros' => $this->restOfSeason ? 'true' : null,
            'positions' => $this->positions === [] ? null : implode(
                ':',
                array_map(static fn (NflPosition $position): string => $position->value, $this->positions),
            ),
            'players' => $this->playerIds === [] ? null : implode(':', $this->playerIds),
        ];

        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

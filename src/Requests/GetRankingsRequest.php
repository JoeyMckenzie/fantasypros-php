<?php

declare(strict_types=1);

namespace FantasyPros\Requests;

use FantasyPros\Data\Envelopes\RankingsCollection;
use FantasyPros\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/{season}/rankings: rankings across ranking types and positions.
 */
final class GetRankingsRequest extends Request
{
    /**
     * The `type` parameter here isn't the ranking-type vocabulary the other
     * two ranking endpoints take. The spec allows this one value only, so
     * presence is the signal and the constructor takes a boolean.
     *
     * `NflRankingType` does carry a matching `Drafters` case, but accepting the
     * whole 19-value enum here would let callers send values this route rejects.
     */
    private const string DRAFTERS = 'DRAFTERS';

    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  list<int>  $expertIds  restrict the rankings to these experts, via `filters`
     * @param  bool  $minimal  return less information per player, for a smaller response
     * @param  bool  $withRange  include each player's min and max rank
     * @param  bool  $withRankStats  include each ranking's average and standard deviation
     * @param  bool  $includeDrafters  include the Drafters ranking type (NFL only)
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly int $season,
        private readonly ?int $week = null,
        private readonly ?int $playerId = null,
        private readonly array $expertIds = [],
        private readonly bool $minimal = false,
        private readonly bool $withRange = false,
        private readonly bool $withRankStats = false,
        private readonly bool $includeDrafters = false,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/%d/rankings', $this->sport->pathSegment(), $this->season);
    }

    #[Override]
    public function createDtoFromResponse(Response $response): RankingsCollection
    {
        return RankingsCollection::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            // Week 0 is meaningful (preseason), so it must survive the filter.
            'week' => $this->week,
            'player' => $this->playerId,
            'filters' => $this->expertIds === [] ? null : implode(':', $this->expertIds),
            // These three default to false at the API, so switching one off
            // means omitting it rather than sending the string "false".
            'min' => $this->minimal ? 'true' : null,
            'range' => $this->withRange ? 'true' : null,
            'rankstats' => $this->withRankStats ? 'true' : null,
            'type' => $this->includeDrafters ? self::DRAFTERS : null,
        ];

        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

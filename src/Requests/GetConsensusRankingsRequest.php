<?php

declare(strict_types=1);

namespace FantasyPros\Requests;

use FantasyPros\Data\Envelopes\ConsensusRankings;
use FantasyPros\Enums\ExpertsDetail;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/{season}/consensus-rankings -- the expert consensus for one position.
 */
final class GetConsensusRankingsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  NflPosition  $position  required by the endpoint, unlike on the experts route
     * @param  list<int>  $expertIds  restrict the consensus to these experts, via `filters`
     * @param  bool  $includeIndividualDefensivePlayers  include IDP positions in the set
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly int $season,
        private readonly NflPosition $position,
        private readonly ?NflRankingType $rankingType = null,
        private readonly ?NflScoringType $scoring = null,
        private readonly ?int $week = null,
        private readonly array $expertIds = [],
        private readonly ?ExpertsDetail $experts = null,
        private readonly bool $includeIndividualDefensivePlayers = false,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/%d/consensus-rankings', $this->sport->pathSegment(), $this->season);
    }

    #[Override]
    public function createDtoFromResponse(Response $response): ConsensusRankings
    {
        return ConsensusRankings::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'position' => $this->position->value,
            'type' => $this->rankingType?->value,
            'scoring' => $this->scoring?->value,
            // Week 0 is meaningful (preseason), so it must survive the filter.
            'week' => $this->week,
            'include_idp' => $this->includeIndividualDefensivePlayers ? 'true' : null,
            'filters' => $this->expertIds === [] ? null : implode(':', $this->expertIds),
            'experts' => $this->experts?->value,
        ];

        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

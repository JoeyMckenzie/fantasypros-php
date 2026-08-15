<?php

declare(strict_types=1);

namespace FantasyPros\Requests;

use FantasyPros\Data\Envelopes\ExpertDirectory;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/{season}/rankings/experts -- profiles of the ranking experts.
 */
final class GetExpertsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  ?NflPosition  $position  optional here, unlike on the consensus-rankings route
     * @param  bool  $withOverallAccuracy  include each expert's overall ranking accuracy
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly int $season,
        private readonly ?NflPosition $position = null,
        private readonly ?NflRankingType $rankingType = null,
        private readonly ?NflScoringType $scoring = null,
        private readonly bool $withOverallAccuracy = false,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/%d/rankings/experts', $this->sport->pathSegment(), $this->season);
    }

    #[Override]
    public function createDtoFromResponse(Response $response): ExpertDirectory
    {
        return ExpertDirectory::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'position' => $this->position?->value,
            'type' => $this->rankingType?->value,
            'scoring' => $this->scoring?->value,
            'include_overall' => $this->withOverallAccuracy ? 'true' : null,
        ];

        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

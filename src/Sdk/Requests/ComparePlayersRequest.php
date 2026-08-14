<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Requests;

use Fantasy\Sdk\Data\PlayerComparison;
use Fantasy\Sdk\Enums\ComparisonDetails;
use Fantasy\Sdk\Enums\ComparisonRankingType;
use Fantasy\Sdk\Enums\NflPosition;
use Fantasy\Sdk\Enums\Sport;
use Fantasy\Sdk\Exceptions\InvalidComparisonException;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/compare-players -- head-to-head expert ranking comparison.
 */
final class ComparePlayersRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  list<int>  $playerIds  FantasyPros player IDs to compare; guarded at
     *                                runtime because these often arrive as MCP tool
     *                                arguments rather than from static call sites
     * @param  list<int>  $expertIds  restrict the comparison to these experts
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly array $playerIds,
        private readonly NflPosition $position,
        private readonly ?ComparisonRankingType $rankingType = null,
        private readonly ?ComparisonDetails $details = null,
        private readonly array $expertIds = [],
        private readonly ?int $year = null,
        private readonly ?int $week = null,
    ) {
        if ($this->playerIds === []) {
            throw InvalidComparisonException::withoutPlayers();
        }
    }

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/compare-players', $this->sport->pathSegment());
    }

    #[Override]
    public function createDtoFromResponse(Response $response): PlayerComparison
    {
        return PlayerComparison::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'players' => implode(':', $this->playerIds),
            'position' => $this->position->value,
            'ranking_type' => $this->rankingType?->value,
            'details' => $this->details?->value,
            'experts' => $this->expertIds === [] ? null : implode(':', $this->expertIds),
            'year' => $this->year,
            // Week 0 is meaningful (preseason), so it must survive the filter.
            'week' => $this->week,
        ];

        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

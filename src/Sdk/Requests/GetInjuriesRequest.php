<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Requests;

use Fantasy\Sdk\Data\InjuryReport;
use Fantasy\Sdk\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/injuries -- injury statuses and, for the NFL, the practice report.
 */
final class GetInjuriesRequest extends Request
{
    /**
     * The `include_minors` and `include_probabilities` parameters are string
     * enums in the spec whose only legal value is this one, so presence is the
     * real signal and the constructor takes booleans.
     */
    private const string ENABLED = 'true';

    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  list<string>  $teamIds  pro team codes, e.g. `SF`; the spec types these
     *                                 as a free-form string rather than an enum
     * @param  list<int>  $playerIds  FantasyPros player IDs
     * @param  bool  $includeMinors  include minor-league players (MLB only)
     * @param  bool  $includeProbabilities  include players on the practice report
     *                                      who carry no injury status (NFL only)
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly ?int $year = null,
        private readonly ?int $week = null,
        private readonly array $teamIds = [],
        private readonly array $playerIds = [],
        private readonly bool $includeMinors = false,
        private readonly bool $includeProbabilities = false,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/injuries', $this->sport->pathSegment());
    }

    #[Override]
    public function createDtoFromResponse(Response $response): InjuryReport
    {
        return InjuryReport::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'year' => $this->year,
            // Week 0 is meaningful (preseason), so it must survive the filter.
            'week' => $this->week,
            'include_minors' => $this->includeMinors ? self::ENABLED : null,
            'include_probabilities' => $this->includeProbabilities ? self::ENABLED : null,
            'team_id' => $this->teamIds === [] ? null : implode(':', $this->teamIds),
            'player_ids' => $this->playerIds === [] ? null : implode(':', $this->playerIds),
        ];

        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

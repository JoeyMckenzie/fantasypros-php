<?php

declare(strict_types=1);

namespace FantasyPros\Concerns;

use FantasyPros\Data\Envelopes\InjuryReport;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetInjuriesRequest;
use Saloon\Http\Connector;

/**
 * The injuries endpoint, in the API's own vocabulary.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsInjuryEndpoints
{
    /**
     * GET /{sport}/injuries: injury statuses and, for the NFL, the practice report.
     *
     * @param  list<string>  $teamIds  pro team codes, e.g. `SF`
     * @param  list<int>  $playerIds  FantasyPros player IDs
     */
    public function injuries(
        Sport $sport,
        ?int $year = null,
        ?int $week = null,
        array $teamIds = [],
        array $playerIds = [],
        bool $includeMinors = false,
        bool $includeProbabilities = false,
    ): InjuryReport {
        $request = new GetInjuriesRequest(
            sport: $sport,
            year: $year,
            week: $week,
            teamIds: $teamIds,
            playerIds: $playerIds,
            includeMinors: $includeMinors,
            includeProbabilities: $includeProbabilities,
        );

        return $request->createDtoFromResponse($this->send($request));
    }
}

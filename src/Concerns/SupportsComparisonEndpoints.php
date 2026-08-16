<?php

declare(strict_types=1);

namespace FantasyPros\Concerns;

use FantasyPros\Data\Envelopes\PlayerComparison;
use FantasyPros\Enums\ComparisonDetails;
use FantasyPros\Enums\ComparisonRankingType;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\ComparePlayersRequest;
use Saloon\Http\Connector;

/**
 * The head-to-head comparison endpoint, in the API's own vocabulary.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsComparisonEndpoints
{
    /**
     * GET /{sport}/compare-players: head-to-head expert ranking comparison.
     *
     * @param  list<int>  $playerIds  FantasyPros player IDs to compare
     * @param  list<int>  $expertIds  restrict the comparison to these experts
     */
    public function comparePlayers(
        Sport $sport,
        array $playerIds,
        NflPosition $position,
        ?ComparisonRankingType $rankingType = null,
        ?ComparisonDetails $details = null,
        array $expertIds = [],
        ?int $year = null,
        ?int $week = null,
    ): PlayerComparison {
        $request = new ComparePlayersRequest(
            sport: $sport,
            playerIds: $playerIds,
            position: $position,
            rankingType: $rankingType,
            details: $details,
            expertIds: $expertIds,
            year: $year,
            week: $week,
        );

        return $request->createDtoFromResponse($this->send($request));
    }
}

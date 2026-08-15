<?php

declare(strict_types=1);

namespace FantasyPros\Concerns;

use FantasyPros\Data\Envelopes\PlayerPointsCollection;
use FantasyPros\Data\Envelopes\ProjectionSet;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetPlayerPointsRequest;
use FantasyPros\Requests\GetProjectionsRequest;
use Saloon\Http\Connector;

/**
 * The two scoring endpoints, in the API's own vocabulary.
 *
 * They are grouped because they are the same question pointed in opposite
 * directions: `projections()` returns what a player is expected to score, and
 * `playerPoints()` returns what they actually did.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsProjectionEndpoints
{
    /**
     * GET /{sport}/{season}/projections -- projected stat lines for a position.
     *
     * @param  list<NflPosition>  $positions  narrow further to these positions
     * @param  list<int>  $playerIds  restrict the set to these players
     */
    public function projections(
        Sport $sport,
        int $season,
        NflPosition $position,
        ?int $week = null,
        bool $restOfSeason = false,
        array $positions = [],
        array $playerIds = [],
    ): ProjectionSet {
        $request = new GetProjectionsRequest(
            sport: $sport,
            season: $season,
            position: $position,
            week: $week,
            restOfSeason: $restOfSeason,
            positions: $positions,
            playerIds: $playerIds,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /{sport}/{season}/player-points -- points actually scored, week by week.
     */
    public function playerPoints(
        Sport $sport,
        int $season,
        ?int $startWeek = null,
        ?int $endWeek = null,
        ?NflPosition $position = null,
        ?NflScoringType $scoring = null,
        bool $minimal = false,
    ): PlayerPointsCollection {
        $request = new GetPlayerPointsRequest(
            sport: $sport,
            season: $season,
            startWeek: $startWeek,
            endWeek: $endWeek,
            position: $position,
            scoring: $scoring,
            minimal: $minimal,
        );

        return $request->createDtoFromResponse($this->send($request));
    }
}

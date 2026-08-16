<?php

declare(strict_types=1);

namespace FantasyPros\Concerns;

use FantasyPros\Data\Envelopes\ConsensusRankings;
use FantasyPros\Data\Envelopes\RankingsCollection;
use FantasyPros\Enums\ExpertsDetail;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetConsensusRankingsRequest;
use FantasyPros\Requests\GetRankingsRequest;
use Saloon\Http\Connector;

/**
 * The two ranking endpoints, in the API's own vocabulary.
 *
 * They are grouped because they answer the same question at different
 * resolutions: `rankings()` returns every ranking a player holds, nested
 * metric -> scoring -> position, while `consensusRankings()` returns one flat
 * ranking for the position asked for.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsRankingEndpoints
{
    /**
     * GET /{sport}/{season}/rankings: rankings across ranking types and positions.
     *
     * @param  list<int>  $expertIds  restrict the rankings to these experts
     */
    public function rankings(
        Sport $sport,
        int $season,
        ?int $week = null,
        ?int $playerId = null,
        array $expertIds = [],
        bool $minimal = false,
        bool $withRange = false,
        bool $withRankStats = false,
        bool $includeDrafters = false,
    ): RankingsCollection {
        $request = new GetRankingsRequest(
            sport: $sport,
            season: $season,
            week: $week,
            playerId: $playerId,
            expertIds: $expertIds,
            minimal: $minimal,
            withRange: $withRange,
            withRankStats: $withRankStats,
            includeDrafters: $includeDrafters,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /{sport}/{season}/consensus-rankings: the expert consensus for one position.
     *
     * @param  list<int>  $expertIds  restrict the consensus to these experts
     */
    public function consensusRankings(
        Sport $sport,
        int $season,
        NflPosition $position,
        ?NflRankingType $rankingType = null,
        ?NflScoringType $scoring = null,
        ?int $week = null,
        array $expertIds = [],
        ?ExpertsDetail $experts = null,
        bool $includeIndividualDefensivePlayers = false,
    ): ConsensusRankings {
        $request = new GetConsensusRankingsRequest(
            sport: $sport,
            season: $season,
            position: $position,
            rankingType: $rankingType,
            scoring: $scoring,
            week: $week,
            expertIds: $expertIds,
            experts: $experts,
            includeIndividualDefensivePlayers: $includeIndividualDefensivePlayers,
        );

        return $request->createDtoFromResponse($this->send($request));
    }
}

<?php

declare(strict_types=1);

namespace FantasyPros\Concerns;

use FantasyPros\Data\Envelopes\ExpertDirectory;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetExpertsRequest;
use Saloon\Http\Connector;

/**
 * The experts directory, in the API's own vocabulary.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsExpertEndpoints
{
    /**
     * GET /{sport}/{season}/rankings/experts -- profiles of the ranking experts.
     *
     * `$rankingType` narrows to the experts who published that specific ranking
     * set, so a season whose rankings do not exist yet returns an empty
     * directory for every ranking type rather than an error.
     */
    public function experts(
        Sport $sport,
        int $season,
        ?NflPosition $position = null,
        ?NflRankingType $rankingType = null,
        ?NflScoringType $scoring = null,
        bool $withOverallAccuracy = false,
    ): ExpertDirectory {
        $request = new GetExpertsRequest(
            sport: $sport,
            season: $season,
            position: $position,
            rankingType: $rankingType,
            scoring: $scoring,
            withOverallAccuracy: $withOverallAccuracy,
        );

        return $request->createDtoFromResponse($this->send($request));
    }
}

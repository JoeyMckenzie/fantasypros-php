<?php

declare(strict_types=1);

namespace FantasyPros\Data\Api;

use FantasyPros\Data\Contracts\ApiDataContract;
use FantasyPros\Data\Infrastructure\Payload;

/**
 * One player in a consensus ranking set.
 *
 * The rank range fields are always present here, whatever the request asked
 * for. The rankings endpoint only sends them with `range=true`. They arrive as
 * numeric strings while `rank_ecr` arrives as an int.
 */
final readonly class ConsensusRankedPlayer implements ApiDataContract
{
    /**
     * @param  array<array-key, int>  $expertRanks  contributing expert ID to the rank
     *                                              that expert gave this player
     */
    public function __construct(
        public int $playerId,
        public string $name,
        public string $shortName,
        public ?string $teamId,
        public ?string $positionId,
        public ?string $positions,
        public ?string $eligibility,
        public ?string $yahooPositions,
        public ?string $pageUrl,
        public ?string $yahooId,
        public ?string $cbsPlayerId,
        public ?string $sportsdataId,
        public ?int $byeWeek,
        public ?float $ownedAverage,
        public ?float $ownedEspn,
        public ?float $ownedYahoo,
        public ?string $opponent,
        public ?string $opponentId,
        public ?float $ecrDelta,
        public ?int $rankEcr,
        public ?int $rankMinimum,
        public ?int $rankMaximum,
        public ?float $rankAverage,
        public ?float $rankStandardDeviation,
        public ?int $rankPoints,
        public ?string $positionRank,
        public ?string $startSitGrade,
        public array $expertRanks,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            playerId: $payload->int('player_id'),
            name: $payload->string('player_name'),
            shortName: $payload->string('player_short_name'),
            teamId: $payload->nullableString('player_team_id'),
            positionId: $payload->nullableString('player_position_id'),
            positions: $payload->nullableString('player_positions'),
            eligibility: $payload->nullableString('player_eligibility'),
            yahooPositions: $payload->nullableString('player_yahoo_positions'),
            pageUrl: $payload->nullableString('player_page_url'),
            yahooId: $payload->nullableString('player_yahoo_id'),
            cbsPlayerId: $payload->nullableString('cbs_player_id'),
            sportsdataId: $payload->nullableString('sportsdata_id'),
            byeWeek: $payload->nullableInt('player_bye_week'),
            ownedAverage: $payload->nullableFloat('player_owned_avg'),
            ownedEspn: $payload->nullableFloat('player_owned_espn'),
            ownedYahoo: $payload->nullableFloat('player_owned_yahoo'),
            opponent: $payload->nullableString('player_opponent'),
            opponentId: $payload->nullableString('player_opponent_id'),
            ecrDelta: $payload->nullableFloat('player_ecr_delta'),
            rankEcr: $payload->nullableInt('rank_ecr'),
            rankMinimum: $payload->nullableInt('rank_min'),
            rankMaximum: $payload->nullableInt('rank_max'),
            rankAverage: $payload->nullableFloat('rank_ave'),
            rankStandardDeviation: $payload->nullableFloat('rank_std'),
            rankPoints: $payload->nullableInt('rank_points'),
            positionRank: $payload->nullableString('pos_rank'),
            startSitGrade: $payload->nullableString('start_sit_grade'),
            expertRanks: $payload->intMap('experts'),
        );
    }

    /**
     * How far apart the most and least optimistic experts were, or null when the
     * player carries no range.
     */
    public function rankSpread(): ?int
    {
        if ($this->rankMinimum === null || $this->rankMaximum === null) {
            return null;
        }

        return $this->rankMaximum - $this->rankMinimum;
    }
}

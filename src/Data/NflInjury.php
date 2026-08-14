<?php

declare(strict_types=1);

namespace FantasyPros\Data;

use FantasyPros\Enums\NflInjuryStatus;

/**
 * One NFL injury entry, including the weekly practice report.
 *
 * NFL-shaped on purpose, as `PlayerComparison` is: MLB adds minor-league fields
 * and carries no practice report at all, so a second sport gets its own DTO
 * rather than a stack of nullable NFL fields here.
 */
final readonly class NflInjury implements ApiDataContract
{
    /**
     * @param  string  $status  raw, because the live endpoint returns an empty status
     *                          for players who are only on the practice report --
     *                          a value the spec's enum does not contain
     * @param  list<int>  $injuredReserveWeeks  weeks the player is designated IR for
     * @param  ?float  $probabilityOfPlaying  0 through 1; 0 is a real value, distinct
     *                                        from the null the API sends off-season
     * @param  ?string  $firstPractice  one of `--`, `DNP`, `Limit`, `Full`
     */
    public function __construct(
        public int $playerId,
        public string $yahooId,
        public string $name,
        public ?string $teamId,
        public ?string $positionId,
        public ?int $rank,
        public string $status,
        public string $statusShort,
        public string $injuryType,
        public string $comment,
        public ?string $updatedAt,
        public array $injuredReserveWeeks,
        public ?float $probabilityOfPlaying,
        public ?string $firstPractice,
        public ?string $secondPractice,
        public ?string $thirdPractice,
        public ?string $practiceReportInjuryType,
        public bool $firstPracticeSubmitted,
        public bool $secondPracticeSubmitted,
        public bool $thirdPracticeSubmitted,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            playerId: $payload->int('player_id'),
            yahooId: $payload->string('yahoo_id'),
            name: $payload->string('name'),
            teamId: $payload->nullableString('team_id'),
            positionId: $payload->nullableString('position_id'),
            rank: $payload->nullableInt('rank'),
            status: $payload->string('status'),
            statusShort: $payload->string('status_short'),
            injuryType: $payload->string('injury_type'),
            comment: $payload->string('comment'),
            // Null in practice, despite the spec marking it required -- a
            // practice-report-only player has no injury update to date.
            updatedAt: $payload->nullableString('injury_update_date'),
            injuredReserveWeeks: $payload->ints('ir_weeks'),
            probabilityOfPlaying: $payload->nullableFloat('probability_of_playing'),
            firstPractice: $payload->nullableString('practice_1'),
            secondPractice: $payload->nullableString('practice_2'),
            thirdPractice: $payload->nullableString('practice_3'),
            practiceReportInjuryType: $payload->nullableString('practice_report_injury_type'),
            firstPracticeSubmitted: $payload->bool('team_practice_1_submitted'),
            secondPracticeSubmitted: $payload->bool('team_practice_2_submitted'),
            thirdPracticeSubmitted: $payload->bool('team_practice_3_submitted'),
        );
    }

    /**
     * The status as an enum, or null when the API reports one the spec does not
     * enumerate -- which includes the empty status it genuinely returns for
     * practice-report-only players.
     */
    public function status(): ?NflInjuryStatus
    {
        return NflInjuryStatus::tryFrom($this->status);
    }
}

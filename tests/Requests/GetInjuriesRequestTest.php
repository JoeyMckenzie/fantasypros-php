<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Requests;

use FantasyPros\Data\ApiLimits;
use FantasyPros\Data\InjuryReport;
use FantasyPros\Data\NflInjury;
use FantasyPros\Enums\NflInjuryStatus;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetInjuriesRequest;
use FantasyPros\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetInjuriesRequest::class)]
#[CoversClass(InjuryReport::class)]
#[CoversClass(NflInjury::class)]
#[CoversClass(NflInjuryStatus::class)]
#[CoversClass(ApiLimits::class)]
final class GetInjuriesRequestTest extends RequestTestCase
{
    #[Test]
    public function it_builds_the_injuries_path_for_the_sport(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json/nfl/injuries',
            $this->uriFor(new GetInjuriesRequest(Sport::Nfl)),
        );
    }

    #[Test]
    public function it_colon_joins_the_team_ids(): void
    {
        self::assertSame(
            ['team_id' => 'SF:MIN'],
            $this->queryParametersFor(new GetInjuriesRequest(Sport::Nfl, teamIds: ['SF', 'MIN'])),
        );
    }

    #[Test]
    public function it_colon_joins_the_player_ids(): void
    {
        self::assertSame(
            ['player_ids' => '7354:6880'],
            $this->queryParametersFor(new GetInjuriesRequest(Sport::Nfl, playerIds: [7354, 6880])),
        );
    }

    /**
     * A single ID still goes out unadorned -- the separator only appears
     * between entries.
     */
    #[Test]
    public function a_single_id_carries_no_separator(): void
    {
        self::assertSame(
            ['team_id' => 'SF', 'player_ids' => '7354'],
            $this->queryParametersFor(new GetInjuriesRequest(Sport::Nfl, teamIds: ['SF'], playerIds: [7354])),
        );
    }

    #[Test]
    public function it_sends_every_supported_option(): void
    {
        $request = new GetInjuriesRequest(
            Sport::Nfl,
            year: 2026,
            week: 3,
            teamIds: ['SF', 'MIN'],
            playerIds: [7354, 6880],
            includeMinors: true,
            includeProbabilities: true,
        );

        self::assertSame(
            'year=2026&week=3&include_minors=true&include_probabilities=true'
            .'&team_id=SF%3AMIN&player_ids=7354%3A6880',
            $this->queryFor($request),
        );
    }

    /**
     * Week 0 means preseason, so it has to survive the null filtering.
     */
    #[Test]
    public function it_keeps_week_zero(): void
    {
        self::assertSame(
            ['week' => 0],
            $this->queryParametersFor(new GetInjuriesRequest(Sport::Nfl, week: 0)),
        );
    }

    /**
     * Both flags are presence-signalled: the API's only legal value is `true`,
     * so switching one off means omitting the parameter, not sending `false`.
     */
    #[Test]
    public function the_flags_are_omitted_rather_than_sent_as_false(): void
    {
        self::assertSame(
            [],
            $this->queryParametersFor(new GetInjuriesRequest(Sport::Nfl)),
        );
    }

    #[Test]
    public function each_flag_is_sent_independently(): void
    {
        self::assertSame(
            ['include_minors' => 'true'],
            $this->queryParametersFor(new GetInjuriesRequest(Sport::Nfl, includeMinors: true)),
        );

        self::assertSame(
            ['include_probabilities' => 'true'],
            $this->queryParametersFor(new GetInjuriesRequest(Sport::Nfl, includeProbabilities: true)),
        );
    }

    #[Test]
    public function empty_lists_are_dropped_rather_than_sent_blank(): void
    {
        self::assertSame(
            ['year' => 2026],
            $this->queryParametersFor(new GetInjuriesRequest(Sport::Nfl, year: 2026, teamIds: [], playerIds: [])),
        );
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_report(): void
    {
        $report = $this->preseasonReport();

        self::assertSame(Sport::Nfl, $report->sport);
        self::assertSame(8, $report->count);
        self::assertCount(8, $report->injuries);
    }

    #[Test]
    public function every_field_of_an_injury_is_mapped(): void
    {
        $kittle = $this->preseasonReport()->injuries[0];

        self::assertSame(16499, $kittle->playerId);
        self::assertSame('30259', $kittle->yahooId);
        self::assertSame('George Kittle', $kittle->name);
        self::assertSame('SF', $kittle->teamId);
        self::assertSame('TE', $kittle->positionId);
        self::assertSame(102, $kittle->rank);
        self::assertSame('PUP', $kittle->status);
        self::assertSame('PUP', $kittle->statusShort);
        self::assertSame('', $kittle->injuryType);
        self::assertSame('', $kittle->comment);
        self::assertSame('2026-08-14 19:30:01', $kittle->updatedAt);
        self::assertSame([], $kittle->injuredReserveWeeks);
    }

    /**
     * Off-season the whole practice-report block is null, which is the shape the
     * preseason fixture pins.
     */
    #[Test]
    public function the_practice_report_is_absent_before_the_season(): void
    {
        $kittle = $this->preseasonReport()->injuries[0];

        self::assertNull($kittle->probabilityOfPlaying);
        self::assertNull($kittle->firstPractice);
        self::assertNull($kittle->secondPractice);
        self::assertNull($kittle->thirdPractice);
        self::assertNull($kittle->practiceReportInjuryType);
        self::assertFalse($kittle->firstPracticeSubmitted);
        self::assertFalse($kittle->secondPracticeSubmitted);
        self::assertFalse($kittle->thirdPracticeSubmitted);
    }

    #[Test]
    public function a_player_without_a_rank_hydrates_anyway(): void
    {
        self::assertNull($this->preseasonReport()->injuries[3]->rank);
    }

    #[Test]
    public function an_in_season_row_carries_the_full_practice_report(): void
    {
        $purdy = $this->inSeasonReport()->injuries[2];

        self::assertSame('Brock Purdy', $purdy->name);
        self::assertSame('OUT', $purdy->status);
        self::assertSame('O', $purdy->statusShort);
        self::assertSame(0.27468, $purdy->probabilityOfPlaying);
        self::assertSame('Limit', $purdy->firstPractice);
        self::assertSame('Limit', $purdy->secondPractice);
        self::assertSame('Limit', $purdy->thirdPractice);
        self::assertSame('toe', $purdy->practiceReportInjuryType);
        self::assertTrue($purdy->firstPracticeSubmitted);
        self::assertTrue($purdy->secondPracticeSubmitted);
        self::assertTrue($purdy->thirdPracticeSubmitted);
    }

    /**
     * A zero probability means no chance of playing, which is not the same as
     * the API having no opinion. It must not collapse to null.
     */
    #[Test]
    public function a_zero_probability_survives_as_zero(): void
    {
        self::assertSame(0.0, $this->inSeasonReport()->injuries[3]->probabilityOfPlaying);
        self::assertSame(1.0, $this->inSeasonReport()->injuries[7]->probabilityOfPlaying);
    }

    #[Test]
    public function injured_reserve_weeks_are_read_as_integers(): void
    {
        self::assertSame([8, 9, 10], $this->inSeasonReport()->injuries[1]->injuredReserveWeeks);
    }

    #[Test]
    public function a_known_status_resolves_to_the_enum(): void
    {
        $injuries = $this->inSeasonReport()->injuries;

        self::assertSame(NflInjuryStatus::InjuredReserve, $injuries[1]->status());
        self::assertSame(NflInjuryStatus::Out, $injuries[2]->status());
        self::assertSame(NflInjuryStatus::PhysicallyUnableToPerform, $injuries[4]->status());
        self::assertSame(NflInjuryStatus::Questionable, $injuries[8]->status());
        self::assertSame(NflInjuryStatus::Suspended, $injuries[9]->status());
    }

    /**
     * A player on the practice report with no injury designation comes back with
     * an empty status -- a value the spec's enum does not contain, so hydrating
     * straight into the enum would have thrown on a genuine payload.
     */
    #[Test]
    public function a_status_outside_the_spec_becomes_null_rather_than_throwing(): void
    {
        $mccaffrey = $this->inSeasonReport()->injuries[0];

        self::assertSame('', $mccaffrey->status);
        self::assertNull($mccaffrey->status());
        self::assertSame('DNP', $mccaffrey->firstPractice);
    }

    /**
     * The same field is null rather than a string when the player has no injury
     * update, despite the spec marking it required.
     */
    #[Test]
    public function a_missing_injury_update_date_is_null(): void
    {
        self::assertNull($this->inSeasonReport()->injuries[0]->updatedAt);
    }

    /**
     * The free tier caps the rows returned while still reporting the true count,
     * exactly as the players endpoint does.
     */
    #[Test]
    public function it_reports_when_the_tier_truncated_the_report(): void
    {
        $report = $this->inSeasonReport();

        self::assertSame(27, $report->count);
        self::assertCount(10, $report->injuries);
        self::assertTrue($report->truncated());

        self::assertTrue($report->limits->limited);
        self::assertSame('free', $report->limits->tier);
        self::assertSame(10, $report->limits->limit);
    }

    #[Test]
    public function a_complete_report_is_not_truncated(): void
    {
        self::assertFalse($this->preseasonReport()->truncated());
    }

    private function preseasonReport(): InjuryReport
    {
        return $this->report(
            new GetInjuriesRequest(Sport::Nfl, teamIds: ['SF'], includeProbabilities: true),
            'nfl/injuries',
        );
    }

    private function inSeasonReport(): InjuryReport
    {
        return $this->report(
            new GetInjuriesRequest(Sport::Nfl, year: 2025, week: 10, teamIds: ['SF'], includeProbabilities: true),
            'nfl/injuries-in-season',
        );
    }

    private function report(GetInjuriesRequest $request, string $fixture): InjuryReport
    {
        $report = $this->dtoFrom($request, $fixture);

        self::assertInstanceOf(InjuryReport::class, $report);

        return $report;
    }
}

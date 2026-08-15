<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Requests;

use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\ProjectedPlayer;
use FantasyPros\Data\Envelopes\ProjectionSet;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetProjectionsRequest;
use FantasyPros\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetProjectionsRequest::class)]
#[CoversClass(ProjectionSet::class)]
#[CoversClass(ProjectedPlayer::class)]
#[CoversClass(ApiLimits::class)]
final class GetProjectionsRequestTest extends RequestTestCase
{
    #[Test]
    public function it_builds_the_path_from_the_sport_and_season(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json/nfl/2025/projections',
            $this->uriFor(new GetProjectionsRequest(Sport::Nfl, 2025, NflPosition::RunningBack)),
        );
    }

    #[Test]
    public function the_position_is_always_sent(): void
    {
        self::assertSame(
            ['position' => 'RB'],
            $this->queryParametersFor(new GetProjectionsRequest(Sport::Nfl, 2025, NflPosition::RunningBack)),
        );
    }

    #[Test]
    public function it_sends_every_supported_option(): void
    {
        $request = new GetProjectionsRequest(
            Sport::Nfl,
            2025,
            NflPosition::RunningBack,
            week: 4,
            restOfSeason: true,
            positions: [NflPosition::RunningBack, NflPosition::WideReceiver],
            playerIds: [16393, 22968],
        );

        self::assertSame(
            'position=RB&week=4&ros=true&positions=RB%3AWR&players=16393%3A22968',
            $this->queryFor($request),
        );
    }

    /**
     * Week 0 asks for preseason projections, so it must survive the null filter
     * rather than being mistaken for "no week given".
     */
    #[Test]
    public function it_keeps_week_zero(): void
    {
        self::assertSame(
            ['position' => 'RB', 'week' => 0],
            $this->queryParametersFor(
                new GetProjectionsRequest(Sport::Nfl, 2025, NflPosition::RunningBack, week: 0),
            ),
        );
    }

    #[Test]
    public function the_rest_of_season_toggle_is_omitted_rather_than_sent_as_false(): void
    {
        self::assertSame(
            ['position' => 'RB', 'ros' => 'true'],
            $this->queryParametersFor(
                new GetProjectionsRequest(Sport::Nfl, 2025, NflPosition::RunningBack, restOfSeason: true),
            ),
        );

        self::assertSame(
            ['position' => 'RB'],
            $this->queryParametersFor(
                new GetProjectionsRequest(Sport::Nfl, 2025, NflPosition::RunningBack, restOfSeason: false),
            ),
        );
    }

    #[Test]
    public function it_colon_joins_the_position_and_player_filters(): void
    {
        self::assertSame(
            ['position' => 'RB', 'positions' => 'RB:WR:TE', 'players' => '16393:22968'],
            $this->queryParametersFor(new GetProjectionsRequest(
                Sport::Nfl,
                2025,
                NflPosition::RunningBack,
                positions: [NflPosition::RunningBack, NflPosition::WideReceiver, NflPosition::TightEnd],
                playerIds: [16393, 22968],
            )),
        );
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_projection_set(): void
    {
        $projections = $this->recordedProjections();

        self::assertSame(2025, $projections->season);
        self::assertSame(4, $projections->week);
        self::assertSame(2, $projections->count);
        self::assertSame('RB', $projections->positions);
        // Projections are filed under STD whatever scoring the caller wants;
        // the per-format numbers live on each player instead.
        self::assertSame('STD', $projections->scoring);
        self::assertFalse($projections->restOfSeason);
        self::assertFalse($projections->truncated());
        self::assertCount(2, $projections->players);
    }

    #[Test]
    public function every_field_of_a_projected_player_is_mapped(): void
    {
        $mccaffrey = $this->recordedProjections()->players[0];

        self::assertSame(16393, $mccaffrey->id);
        self::assertSame(13130, $mccaffrey->mflId);
        self::assertSame('Christian McCaffrey', $mccaffrey->name);
        self::assertSame('RB', $mccaffrey->positionId);
        self::assertSame('SF', $mccaffrey->teamId);
        self::assertSame('christian-mccaffrey.php', $mccaffrey->profileUrl);
        self::assertSame(NflPosition::RunningBack, $mccaffrey->position());
    }

    /**
     * Unlike the ranking routes, every position carries all three points keys.
     * A running back is the case where they genuinely differ.
     */
    #[Test]
    public function points_are_readable_per_scoring_format(): void
    {
        $mccaffrey = $this->recordedProjections()->players[0];

        self::assertSame(17.78, $mccaffrey->points());
        self::assertSame(17.78, $mccaffrey->points(NflScoringType::Standard));
        self::assertSame(23.3, $mccaffrey->points(NflScoringType::Ppr));
        self::assertSame(20.54, $mccaffrey->points(NflScoringType::Half));
    }

    #[Test]
    public function the_stat_line_is_readable_by_key(): void
    {
        $mccaffrey = $this->recordedProjections()->players[0];

        self::assertSame(17.65, $mccaffrey->stat('rush_att'));
        self::assertSame(77.66, $mccaffrey->stat('rush_yds'));
        self::assertSame(5.52, $mccaffrey->stat('rec_rec'));
        // Leading with a digit, this key could never have been a property name.
        self::assertSame(0.0, $mccaffrey->stat('2pt_tds'));
    }

    /**
     * The whole reason the stat line is a map. A DST carries a different key set
     * from a running back, and asking for the wrong one answers null rather than
     * erroring.
     */
    #[Test]
    public function a_defence_carries_a_different_stat_line_entirely(): void
    {
        $broncos = $this->recordedDefenceProjections()->players[0];

        self::assertSame('Denver Broncos', $broncos->name);
        self::assertSame('DST', $broncos->positionId);

        self::assertSame(3.75, $broncos->stat('def_sack'));
        self::assertSame(0.96, $broncos->stat('def_int'));
        self::assertSame(18.56, $broncos->stat('def_pa'));
        self::assertSame(311.74, $broncos->stat('def_tyda'));

        // A running back's keys are simply absent here.
        self::assertNull($broncos->stat('rush_yds'));
        self::assertNull($broncos->stat('rec_rec'));

        // And the spec's own DST schema names seven point-allowed buckets that
        // the live route has never returned.
        self::assertNull($broncos->stat('def_pa_a'));
        self::assertNull($broncos->stat('def_pa_g'));
    }

    /**
     * Scoring format only changes what a pass-catcher is worth, so a defence's
     * three points keys hold the same number rather than diverging.
     */
    #[Test]
    public function a_defence_scores_the_same_in_every_format(): void
    {
        $broncos = $this->recordedDefenceProjections()->players[0];

        self::assertSame(9.04, $broncos->points());
        self::assertSame(9.04, $broncos->points(NflScoringType::Ppr));
        self::assertSame(9.04, $broncos->points(NflScoringType::Half));
    }

    /**
     * AC #1. A rest-of-season request is accepted and echoed back, but answers
     * with a literal `null` for `players` once the season has no games left --
     * not an empty array, and not an error. Hydrating that must not throw.
     */
    #[Test]
    public function a_rest_of_season_request_hydrates_an_empty_set(): void
    {
        $projections = $this->recordedRestOfSeasonProjections();

        self::assertTrue($projections->restOfSeason);
        self::assertSame(0, $projections->count);
        self::assertSame(0, $projections->week);
        self::assertSame([], $projections->players);
        self::assertFalse($projections->truncated());
    }

    #[Test]
    public function a_player_without_a_stat_block_has_an_empty_stat_line(): void
    {
        $player = ProjectedPlayer::fromPayload(Payload::of([
            'fpid' => 16393,
            'name' => 'Christian McCaffrey',
            'position_id' => 'RB',
            'team_id' => 'SF',
        ]));

        self::assertSame([], $player->stats);
        self::assertNull($player->points());
        self::assertNull($player->stat('rush_yds'));
        self::assertNull($player->mflId);
        self::assertNull($player->profileUrl);
    }

    #[Test]
    public function an_unrecognised_position_reports_no_enum_case(): void
    {
        $player = ProjectedPlayer::fromPayload(Payload::of([
            'fpid' => 1,
            'name' => 'Someone',
            'position_id' => 'XYZ',
            'team_id' => 'SF',
        ]));

        self::assertNull($player->position());
    }

    /**
     * The recorded sets are complete, so truncation is pinned directly rather
     * than left to a fixture that cannot show it.
     */
    #[Test]
    public function a_capped_set_reports_itself_truncated(): void
    {
        self::assertTrue($this->projectionSetOf(count: 3, players: 2)->truncated());
        self::assertFalse($this->projectionSetOf(count: 2, players: 2)->truncated());
    }

    private function projectionSetOf(int $count, int $players): ProjectionSet
    {
        return new ProjectionSet(
            season: 2025,
            week: 4,
            count: $count,
            positions: 'RB',
            scoring: 'STD',
            restOfSeason: false,
            players: array_fill(0, $players, ProjectedPlayer::fromPayload(Payload::of([
                'fpid' => 1,
                'name' => 'Someone',
                'position_id' => 'RB',
                'team_id' => 'SF',
            ]))),
            limits: new ApiLimits(false, null, null),
        );
    }

    private function recordedProjections(): ProjectionSet
    {
        $projections = $this->dtoFrom(
            new GetProjectionsRequest(
                Sport::Nfl,
                2025,
                NflPosition::RunningBack,
                week: 4,
                playerIds: [16393, 22968],
            ),
            'nfl/projections',
        );

        self::assertInstanceOf(ProjectionSet::class, $projections);

        return $projections;
    }

    private function recordedDefenceProjections(): ProjectionSet
    {
        $projections = $this->dtoFrom(
            new GetProjectionsRequest(
                Sport::Nfl,
                2025,
                NflPosition::Defense,
                week: 4,
                playerIds: [8090],
            ),
            'nfl/projections-dst',
        );

        self::assertInstanceOf(ProjectionSet::class, $projections);

        return $projections;
    }

    private function recordedRestOfSeasonProjections(): ProjectionSet
    {
        $projections = $this->dtoFrom(
            new GetProjectionsRequest(
                Sport::Nfl,
                2025,
                NflPosition::RunningBack,
                restOfSeason: true,
            ),
            'nfl/projections-ros',
        );

        self::assertInstanceOf(ProjectionSet::class, $projections);

        return $projections;
    }
}

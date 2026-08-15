<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Requests;

use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\PlayerPoints;
use FantasyPros\Data\Envelopes\PlayerPointsCollection;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetPlayerPointsRequest;
use FantasyPros\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetPlayerPointsRequest::class)]
#[CoversClass(PlayerPointsCollection::class)]
#[CoversClass(PlayerPoints::class)]
#[CoversClass(ApiLimits::class)]
final class GetPlayerPointsRequestTest extends RequestTestCase
{
    #[Test]
    public function it_builds_the_path_from_the_sport_and_season(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json/nfl/2025/player-points',
            $this->uriFor(new GetPlayerPointsRequest(Sport::Nfl, 2025)),
        );
    }

    /**
     * Every parameter is optional here, unlike projections -- the API defaults
     * to all positions across the whole regular season.
     */
    #[Test]
    public function it_sends_nothing_when_nothing_is_asked_for(): void
    {
        self::assertSame([], $this->queryParametersFor(new GetPlayerPointsRequest(Sport::Nfl, 2025)));
    }

    #[Test]
    public function it_sends_every_supported_option(): void
    {
        $request = new GetPlayerPointsRequest(
            Sport::Nfl,
            2025,
            startWeek: 1,
            endWeek: 4,
            position: NflPosition::Quarterback,
            scoring: NflScoringType::Ppr,
            minimal: true,
        );

        self::assertSame(
            'start=1&end=4&position=QB&scoring=PPR&min=true',
            $this->queryFor($request),
        );
    }

    #[Test]
    public function the_minimal_toggle_is_omitted_rather_than_sent_as_false(): void
    {
        self::assertSame(
            ['min' => 'true'],
            $this->queryParametersFor(new GetPlayerPointsRequest(Sport::Nfl, 2025, minimal: true)),
        );

        self::assertSame(
            [],
            $this->queryParametersFor(new GetPlayerPointsRequest(Sport::Nfl, 2025, minimal: false)),
        );
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_points_collection(): void
    {
        $points = $this->recordedPoints();

        self::assertSame(2025, $points->season);
        self::assertSame('PPR', $points->scoring);
        self::assertNotSame([], $points->players);
    }

    #[Test]
    public function every_field_of_a_scoring_player_is_mapped(): void
    {
        $hurts = $this->recordedPoints()->players[0];

        self::assertSame(19275, $hurts->id);
        self::assertSame('Jalen Hurts', $hurts->name);
        self::assertSame('QB', $hurts->positionId);
        self::assertSame('PHI', $hurts->teamId);
        self::assertSame('jalen-hurts.php', $hurts->profileUrl);
        self::assertSame(4, $hurts->games);
        self::assertSame(NflPosition::Quarterback, $hurts->position());
    }

    /**
     * AC #2. The weekly breakdown is an object keyed by week number, and only
     * the weeks a player actually played appear in it.
     */
    #[Test]
    public function the_weekly_breakdown_hydrates(): void
    {
        $hurts = $this->recordedPoints()->players[0];

        self::assertSame([1, 2, 3, 4], array_keys($hurts->weeks));
        self::assertSame(24.3, $hurts->inWeek(1));
        self::assertSame(11.5, $hurts->inWeek(2));
        self::assertSame(29.0, $hurts->inWeek(3));
        self::assertSame(19.4, $hurts->inWeek(4));
        self::assertSame(29.0, $hurts->bestWeek());
    }

    #[Test]
    public function a_week_outside_the_requested_range_has_no_points(): void
    {
        self::assertNull($this->recordedPoints()->players[0]->inWeek(9));
    }

    /**
     * The spec marks `games`, `points`, `average` and `weeks` required. A player
     * who did not appear in the requested range arrives with none of them --
     * identity only -- so the recorded set is checked for that directly rather
     * than trusting the schema.
     */
    #[Test]
    public function a_player_who_did_not_appear_arrives_as_an_identity_alone(): void
    {
        $players = $this->recordedPoints()->players;

        $absent = array_values(array_filter(
            $players,
            static fn (PlayerPoints $player): bool => $player->weeks === [],
        ));

        self::assertCount(26, $absent, 'The recorded quarterback set has 26 players who did not play.');
        self::assertCount(86, $players);

        $mills = $absent[0];

        self::assertSame(22799, $mills->id);
        self::assertSame('Davis Mills', $mills->name);
        // Present in the payload, so the identity block survives.
        self::assertSame('HOU', $mills->teamId);
        // Absent from the payload, so these are the substituted scoreless line.
        self::assertSame(0, $mills->games);
        self::assertSame(0.0, $mills->points);
        self::assertSame(0.0, $mills->average);
        self::assertNull($mills->bestWeek());
    }

    /**
     * `min=true` drops the name, position and team, which is why they are
     * nullable. Pinned directly rather than by recording a second fixture whose
     * only difference is what it omits.
     */
    #[Test]
    public function a_minimal_player_keeps_only_its_identity_and_numbers(): void
    {
        $player = PlayerPoints::fromPayload(Payload::of([
            'player_id' => 19275,
            'games' => 2,
            'points' => 35.8,
            'average' => 17.9,
            'weeks' => ['1' => 24.3, '2' => 11.5],
        ]));

        self::assertSame(19275, $player->id);
        self::assertNull($player->name);
        self::assertNull($player->positionId);
        self::assertNull($player->teamId);
        self::assertNull($player->profileUrl);
        self::assertNull($player->position());

        self::assertSame(2, $player->games);
        self::assertSame(35.8, $player->points);
        self::assertSame(17.9, $player->average);
        self::assertSame([1, 2], array_keys($player->weeks));
    }

    #[Test]
    public function a_player_who_has_not_played_has_no_best_week(): void
    {
        $player = PlayerPoints::fromPayload(Payload::of([
            'player_id' => 1,
            'games' => 0,
            'points' => 0,
            'average' => 0,
        ]));

        self::assertSame([], $player->weeks);
        self::assertNull($player->bestWeek());
        self::assertNull($player->inWeek(1));
        self::assertSame(0.0, $player->points);
        self::assertSame(0.0, $player->average);
    }

    /**
     * The weeks map arrives with numeric string keys, which PHP casts to int on
     * the way into an array -- so `inWeek()` looking them up by int is correct
     * rather than accidental.
     */
    #[Test]
    public function week_keys_are_readable_as_integers(): void
    {
        $player = PlayerPoints::fromPayload(Payload::of([
            'player_id' => 1,
            'games' => 1,
            'points' => 6.0,
            'average' => 6.0,
            'weeks' => ['17' => 6.0],
        ]));

        self::assertSame(6.0, $player->inWeek(17));
        self::assertSame([17], array_keys($player->weeks));
    }

    private function recordedPoints(): PlayerPointsCollection
    {
        $points = $this->dtoFrom(
            new GetPlayerPointsRequest(
                Sport::Nfl,
                2025,
                startWeek: 1,
                endWeek: 4,
                position: NflPosition::Quarterback,
                scoring: NflScoringType::Ppr,
            ),
            'nfl/player-points',
        );

        self::assertInstanceOf(PlayerPointsCollection::class, $points);

        return $points;
    }
}

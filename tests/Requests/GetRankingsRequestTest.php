<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Requests;

use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\RankedPlayer;
use FantasyPros\Data\Envelopes\RankingsCollection;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\RankMetric;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetRankingsRequest;
use FantasyPros\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetRankingsRequest::class)]
#[CoversClass(RankingsCollection::class)]
#[CoversClass(RankedPlayer::class)]
#[CoversClass(RankMetric::class)]
#[CoversClass(ApiLimits::class)]
final class GetRankingsRequestTest extends RequestTestCase
{
    /**
     * @return iterable<string, array{Sport, int, string}>
     */
    public static function seasonPaths(): iterable
    {
        yield 'nfl 2026' => [Sport::Nfl, 2026, 'https://api.fantasypros.com/public/v2/json/nfl/2026/rankings'];
        yield 'nfl 2012' => [Sport::Nfl, 2012, 'https://api.fantasypros.com/public/v2/json/nfl/2012/rankings'];
        yield 'mlb 2025' => [Sport::Mlb, 2025, 'https://api.fantasypros.com/public/v2/json/mlb/2025/rankings'];
    }

    /**
     * The season is a path segment here rather than a query parameter, making
     * this the first route in the SDK to carry anything beyond the sport.
     */
    #[Test]
    #[DataProvider('seasonPaths')]
    public function it_builds_the_path_from_the_sport_and_season(Sport $sport, int $season, string $expected): void
    {
        self::assertSame($expected, $this->uriFor(new GetRankingsRequest($sport, $season)));
    }

    #[Test]
    public function it_sends_every_supported_option(): void
    {
        $request = new GetRankingsRequest(
            Sport::Nfl,
            2026,
            week: 3,
            playerId: 17240,
            expertIds: [345, 332],
            minimal: true,
            withRange: true,
            withRankStats: true,
            includeDrafters: true,
        );

        self::assertSame(
            'week=3&player=17240&filters=345%3A332&min=true&range=true&rankstats=true&type=DRAFTERS',
            $this->queryFor($request),
        );
    }

    #[Test]
    public function it_registers_no_parameters_when_nothing_is_asked_for(): void
    {
        self::assertSame([], $this->queryParametersFor(new GetRankingsRequest(Sport::Nfl, 2026)));
    }

    /**
     * Week 0 means preseason, so it has to survive the null filtering.
     */
    #[Test]
    public function it_keeps_week_zero(): void
    {
        self::assertSame(
            ['week' => 0],
            $this->queryParametersFor(new GetRankingsRequest(Sport::Nfl, 2026, week: 0)),
        );
    }

    #[Test]
    public function it_colon_joins_the_expert_filter(): void
    {
        self::assertSame(
            ['filters' => '345:332:12'],
            $this->queryParametersFor(new GetRankingsRequest(Sport::Nfl, 2026, expertIds: [345, 332, 12])),
        );
    }

    #[Test]
    public function an_empty_expert_filter_is_dropped(): void
    {
        self::assertSame([], $this->queryParametersFor(new GetRankingsRequest(Sport::Nfl, 2026, expertIds: [])));
    }

    /**
     * All three toggles default to false at the API, so switching one off means
     * omitting it rather than sending the string "false".
     */
    #[Test]
    public function each_toggle_is_sent_independently_and_omitted_when_off(): void
    {
        self::assertSame(
            ['min' => 'true'],
            $this->queryParametersFor(new GetRankingsRequest(Sport::Nfl, 2026, minimal: true)),
        );

        self::assertSame(
            ['range' => 'true'],
            $this->queryParametersFor(new GetRankingsRequest(Sport::Nfl, 2026, withRange: true)),
        );

        self::assertSame(
            ['rankstats' => 'true'],
            $this->queryParametersFor(new GetRankingsRequest(Sport::Nfl, 2026, withRankStats: true)),
        );

        self::assertSame(
            ['type' => 'DRAFTERS'],
            $this->queryParametersFor(new GetRankingsRequest(Sport::Nfl, 2026, includeDrafters: true)),
        );
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_collection(): void
    {
        $rankings = $this->recordedRankings();

        self::assertSame(Sport::Nfl, $rankings->sport);
        self::assertSame(2025, $rankings->season);
        self::assertSame(10, $rankings->week);
        self::assertCount(1, $rankings->players);
        self::assertFalse($rankings->truncated());
        self::assertSame('free', $rankings->limits->tier);
    }

    #[Test]
    public function every_identity_field_of_a_ranked_player_is_mapped(): void
    {
        $barkley = $this->recordedRankings()->players[0];

        self::assertSame(17240, $barkley->id);
        self::assertSame('Saquon Barkley', $barkley->name);
        self::assertSame('S. Barkley', $barkley->shortName);
        self::assertSame('Saquon', $barkley->firstName);
        self::assertSame('Barkley', $barkley->lastName);
        self::assertSame('Barkley, Saquon', $barkley->reverseName);
        self::assertSame('RB', $barkley->positionId);
        self::assertSame(['RB'], $barkley->positions);
        self::assertSame('PHI', $barkley->teamId);
        self::assertSame('http://www.fantasypros.com/nfl/players/saquon-barkley.php', $barkley->profileUrl);
        self::assertSame(NflPosition::RunningBack, $barkley->position());
    }

    /**
     * The rank object is three levels deep (metric, then scoring, then
     * position), which the spec doesn't describe at all.
     */
    #[Test]
    public function the_consensus_rank_is_read_through_the_nesting(): void
    {
        $barkley = $this->recordedRankings()->players[0];

        self::assertSame(8.0, $barkley->rank(RankMetric::Consensus, 'STD', 'RB'));
        self::assertSame(9.0, $barkley->rank(RankMetric::Consensus, 'PPR', 'RB'));
        self::assertSame(16.0, $barkley->rank(RankMetric::Consensus, 'PPR', 'FLX'));
        self::assertSame(9.0, $barkley->consensusRank('PPR'));
    }

    /**
     * The scoring level carries keys well beyond STD/PPR/HALF, none of them
     * enumerated in the spec, which is why they stay strings.
     */
    #[Test]
    public function the_undocumented_scoring_keys_are_readable(): void
    {
        $barkley = $this->recordedRankings()->players[0];

        self::assertSame(9.0, $barkley->rank(RankMetric::Consensus, 'ROS-STD', 'ALL'));
        self::assertSame(17.0, $barkley->rank(RankMetric::Consensus, 'DYN', 'ALL'));
    }

    /**
     * The fixture was recorded with both range and rankstats on, so all five
     * metric blocks are present.
     */
    #[Test]
    public function the_range_and_stats_metrics_hydrate_when_requested(): void
    {
        $barkley = $this->recordedRankings()->players[0];

        self::assertSame(4.0, $barkley->rank(RankMetric::Minimum, 'STD', 'RB'));
        self::assertSame(11.0, $barkley->rank(RankMetric::Maximum, 'STD', 'RB'));
        self::assertSame(1.5, $barkley->rank(RankMetric::StandardDeviation, 'STD', 'RB'));
        // ECR_AVG mixes plain ints and decimals inside one block, so the reader
        // has to take both.
        self::assertSame(8.0, $barkley->rank(RankMetric::Average, 'STD', 'RB'));
        self::assertSame(8.7, $barkley->rank(RankMetric::Average, 'STD', 'FLX'));
    }

    #[Test]
    public function an_unranked_combination_is_null_rather_than_an_error(): void
    {
        $barkley = $this->recordedRankings()->players[0];

        self::assertNull($barkley->rank(RankMetric::Consensus, 'STD', 'QB'));
        self::assertNull($barkley->rank(RankMetric::Consensus, 'NOT-A-FORMAT', 'RB'));
    }

    private function recordedRankings(): RankingsCollection
    {
        $rankings = $this->dtoFrom(
            new GetRankingsRequest(
                Sport::Nfl,
                2025,
                week: 10,
                playerId: 17240,
                withRange: true,
                withRankStats: true,
            ),
            'nfl/rankings',
        );

        self::assertInstanceOf(RankingsCollection::class, $rankings);

        return $rankings;
    }
}

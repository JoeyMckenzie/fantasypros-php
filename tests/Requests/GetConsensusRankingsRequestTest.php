<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Requests;

use FantasyPros\Data\Api\ConsensusRankedPlayer;
use FantasyPros\Data\Api\RankingExpert;
use FantasyPros\Data\Envelopes\ConsensusRankings;
use FantasyPros\Data\Infrastructure\ApiLimits;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\ExpertsDetail;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetConsensusRankingsRequest;
use FantasyPros\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetConsensusRankingsRequest::class)]
#[CoversClass(ConsensusRankings::class)]
#[CoversClass(ConsensusRankedPlayer::class)]
#[CoversClass(RankingExpert::class)]
#[CoversClass(ApiLimits::class)]
#[CoversClass(ExpertsDetail::class)]
final class GetConsensusRankingsRequestTest extends RequestTestCase
{
    #[Test]
    public function it_builds_the_path_from_the_sport_and_season(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json/nfl/2026/consensus-rankings',
            $this->uriFor(new GetConsensusRankingsRequest(Sport::Nfl, 2026, NflPosition::RunningBack)),
        );
    }

    /**
     * Position is the one parameter the endpoint requires, so it is always sent
     * even when nothing else is.
     */
    #[Test]
    public function the_position_is_always_sent(): void
    {
        self::assertSame(
            ['position' => 'RB'],
            $this->queryParametersFor(new GetConsensusRankingsRequest(Sport::Nfl, 2026, NflPosition::RunningBack)),
        );
    }

    #[Test]
    public function it_sends_every_supported_option(): void
    {
        $request = new GetConsensusRankingsRequest(
            Sport::Nfl,
            2026,
            NflPosition::RunningBack,
            NflRankingType::Draft,
            NflScoringType::Ppr,
            week: 3,
            expertIds: [345, 332],
            experts: ExpertsDetail::Show,
            includeIndividualDefensivePlayers: true,
        );

        self::assertSame(
            'position=RB&type=DRAFT&scoring=PPR&week=3&include_idp=true&filters=345%3A332&experts=show',
            $this->queryFor($request),
        );
    }

    #[Test]
    public function it_keeps_week_zero(): void
    {
        self::assertSame(
            ['position' => 'RB', 'week' => 0],
            $this->queryParametersFor(
                new GetConsensusRankingsRequest(Sport::Nfl, 2026, NflPosition::RunningBack, week: 0),
            ),
        );
    }

    #[Test]
    public function it_colon_joins_the_expert_filter(): void
    {
        self::assertSame(
            ['position' => 'RB', 'filters' => '345:332'],
            $this->queryParametersFor(
                new GetConsensusRankingsRequest(Sport::Nfl, 2026, NflPosition::RunningBack, expertIds: [345, 332]),
            ),
        );
    }

    #[Test]
    public function the_idp_toggle_is_omitted_rather_than_sent_as_false(): void
    {
        self::assertSame(
            ['position' => 'RB', 'include_idp' => 'true'],
            $this->queryParametersFor(new GetConsensusRankingsRequest(
                Sport::Nfl,
                2026,
                NflPosition::RunningBack,
                includeIndividualDefensivePlayers: true,
            )),
        );

        self::assertSame(
            ['position' => 'RB'],
            $this->queryParametersFor(new GetConsensusRankingsRequest(
                Sport::Nfl,
                2026,
                NflPosition::RunningBack,
                includeIndividualDefensivePlayers: false,
            )),
        );
    }

    #[Test]
    public function the_experts_detail_is_sent_as_its_own_value(): void
    {
        self::assertSame(
            ['position' => 'RB', 'experts' => 'available'],
            $this->queryParametersFor(new GetConsensusRankingsRequest(
                Sport::Nfl,
                2026,
                NflPosition::RunningBack,
                experts: ExpertsDetail::Available,
            )),
        );
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_ranking_set(): void
    {
        $rankings = $this->recordedRankings();

        self::assertSame(Sport::Nfl, $rankings->sport);
        // `type` is a display label while `ranking_type_name` is the machine
        // name -- and the latter is lowercase, not the uppercase vocabulary the
        // spec types it as.
        self::assertSame('Weekly PPR', $rankings->label);
        self::assertSame('weekly', $rankings->rankingType);
        self::assertSame(2025, $rankings->year);
        self::assertSame(10, $rankings->week);
        self::assertSame('RB', $rankings->positionId);
        self::assertSame('PPR', $rankings->scoring);
        self::assertSame(2, $rankings->totalExperts);
        self::assertSame('11/09', $rankings->lastUpdated);
        self::assertSame(1762710199, $rankings->lastUpdatedTimestamp);
    }

    /**
     * The filter has to be sent colon-joined, but the API echoes back the list
     * it applied comma-joined. Both halves of that asymmetry are pinned here.
     */
    #[Test]
    public function the_applied_expert_filter_is_echoed_back_comma_joined(): void
    {
        self::assertSame('1091,2791', $this->recordedRankings()->filters);
    }

    #[Test]
    public function every_field_of_a_ranked_player_is_mapped(): void
    {
        $mccaffrey = $this->recordedRankings()->players[0];

        self::assertSame(16393, $mccaffrey->playerId);
        self::assertSame('Christian McCaffrey', $mccaffrey->name);
        self::assertSame('C. McCaffrey', $mccaffrey->shortName);
        self::assertSame('SF', $mccaffrey->teamId);
        self::assertSame('RB', $mccaffrey->positionId);
        self::assertSame('RB', $mccaffrey->positions);
        self::assertSame('RB', $mccaffrey->eligibility);
        self::assertSame('https://www.fantasypros.com/nfl/players/christian-mccaffrey.php', $mccaffrey->pageUrl);
        self::assertSame('30121', $mccaffrey->yahooId);
        self::assertSame('2136743', $mccaffrey->cbsPlayerId);
        self::assertSame('f96db0af-5e25-42d1-a07a-49b4e065b364', $mccaffrey->sportsdataId);
        self::assertSame(14, $mccaffrey->byeWeek);
        self::assertSame(99.5, $mccaffrey->ownedAverage);
        self::assertSame(99.8, $mccaffrey->ownedEspn);
        self::assertSame('vs. LAR', $mccaffrey->opponent);
        self::assertSame('LAR', $mccaffrey->opponentId);
        self::assertSame(104, $mccaffrey->rankPoints);
        self::assertSame('RB1', $mccaffrey->positionRank);
        self::assertSame('A+', $mccaffrey->startSitGrade);
    }

    /**
     * AC #2. The range fields are present on every player whatever the request
     * asked for -- unlike the rankings endpoint, where they need `range=true`.
     */
    #[Test]
    public function the_rank_range_hydrates_without_being_asked_for(): void
    {
        $jeanty = $this->recordedRankings()->players[9];

        self::assertSame('Ashton Jeanty', $jeanty->name);
        self::assertSame(10, $jeanty->rankEcr);
        // Sent as numeric strings on the wire, unlike rank_ecr.
        self::assertSame(8, $jeanty->rankMinimum);
        self::assertSame(13, $jeanty->rankMaximum);
        self::assertSame(2.5, $jeanty->rankStandardDeviation);
        self::assertSame(5, $jeanty->rankSpread());
    }

    #[Test]
    public function a_unanimous_player_has_no_spread(): void
    {
        self::assertSame(0, $this->recordedRankings()->players[0]->rankSpread());
        self::assertSame(1.0, $this->recordedRankings()->players[0]->rankAverage);
    }

    #[Test]
    public function each_players_contributing_expert_ranks_are_mapped(): void
    {
        self::assertSame(
            [1091 => 6, 2791 => 9],
            $this->recordedRankings()->players[6]->expertRanks,
        );
    }

    /**
     * The three parallel ID-keyed maps on the envelope fold into one entry per
     * expert. Note the payload's key is `expert_names`, not the `expert_name`
     * the spec documents.
     */
    #[Test]
    public function the_parallel_expert_maps_fold_into_one_record_each(): void
    {
        $experts = $this->recordedRankings()->experts;

        self::assertCount(2, $experts);

        $hauff = $experts[1091];

        self::assertSame(1091, $hauff->id);
        self::assertSame('Michael Hauff', $hauff->name);
        self::assertSame('TheFFRealist', $hauff->twitter);
        self::assertSame('2025-11-09 17:43:19', $hauff->publishedAt);
    }

    #[Test]
    public function it_reports_when_the_tier_truncated_the_set(): void
    {
        $rankings = $this->recordedRankings();

        self::assertSame(61, $rankings->count);
        self::assertCount(10, $rankings->players);
        self::assertTrue($rankings->truncated());
        self::assertSame(10, $rankings->limits->limit);
        self::assertSame('free', $rankings->limits->tier);
    }

    /**
     * Every player in the recorded set carries both bounds, so the guard that
     * keeps `rankSpread()` from subtracting a null is pinned directly.
     */
    #[Test]
    public function a_player_missing_either_bound_has_no_spread(): void
    {
        self::assertNull($this->playerWithBounds(rankMinimum: 5, rankMaximum: null)->rankSpread());
        self::assertNull($this->playerWithBounds(rankMinimum: null, rankMaximum: 9)->rankSpread());
        self::assertNull($this->playerWithBounds(rankMinimum: null, rankMaximum: null)->rankSpread());
        self::assertSame(4, $this->playerWithBounds(rankMinimum: 5, rankMaximum: 9)->rankSpread());
    }

    /**
     * The recorded set is truncated, so the un-truncated case is pinned here
     * rather than left to a fixture that cannot show it.
     */
    #[Test]
    public function a_complete_set_is_not_truncated(): void
    {
        self::assertFalse($this->rankingsOf(count: 2, players: 2)->truncated());
        self::assertTrue($this->rankingsOf(count: 3, players: 2)->truncated());
    }

    private function playerWithBounds(?int $rankMinimum, ?int $rankMaximum): ConsensusRankedPlayer
    {
        return ConsensusRankedPlayer::fromPayload(Payload::of([
            'player_id' => 16393,
            'player_name' => 'Christian McCaffrey',
            'player_short_name' => 'C. McCaffrey',
            'rank_min' => $rankMinimum,
            'rank_max' => $rankMaximum,
        ]));
    }

    private function rankingsOf(int $count, int $players): ConsensusRankings
    {
        return new ConsensusRankings(
            sport: Sport::Nfl,
            label: 'Weekly PPR',
            rankingType: 'weekly',
            year: 2025,
            week: 10,
            positionId: 'RB',
            scoring: 'PPR',
            filters: null,
            count: $count,
            totalExperts: 2,
            lastUpdated: null,
            lastUpdatedTimestamp: null,
            players: array_fill(0, $players, $this->playerWithBounds(1, 1)),
            experts: [],
            limits: new ApiLimits(false, null, null),
        );
    }

    private function recordedRankings(): ConsensusRankings
    {
        $rankings = $this->dtoFrom(
            new GetConsensusRankingsRequest(
                Sport::Nfl,
                2025,
                NflPosition::RunningBack,
                scoring: NflScoringType::Ppr,
                week: 10,
                expertIds: [1091, 2791],
                experts: ExpertsDetail::Show,
            ),
            'nfl/consensus-rankings',
        );

        self::assertInstanceOf(ConsensusRankings::class, $rankings);

        return $rankings;
    }
}

<?php

declare(strict_types=1);

namespace Fantasy\Tests\Sdk\Requests;

use Fantasy\Sdk\Data\ComparedExpert;
use Fantasy\Sdk\Data\ComparedPlayer;
use Fantasy\Sdk\Data\ExpertRank;
use Fantasy\Sdk\Data\PlayerComparison;
use Fantasy\Sdk\Enums\ComparisonDetails;
use Fantasy\Sdk\Enums\ComparisonRankingType;
use Fantasy\Sdk\Enums\NflPosition;
use Fantasy\Sdk\Enums\NflScoringType;
use Fantasy\Sdk\Enums\Sport;
use Fantasy\Sdk\Exceptions\InvalidComparisonException;
use Fantasy\Sdk\Requests\ComparePlayersRequest;
use Fantasy\Tests\Sdk\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ComparePlayersRequest::class)]
#[CoversClass(PlayerComparison::class)]
#[CoversClass(ComparedPlayer::class)]
#[CoversClass(ComparedExpert::class)]
#[CoversClass(ExpertRank::class)]
#[CoversClass(InvalidComparisonException::class)]
final class ComparePlayersRequestTest extends RequestTestCase
{
    #[Test]
    public function it_builds_the_compare_players_path_for_the_sport(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json/nfl/compare-players',
            $this->uriFor($this->comparison()),
        );
    }

    #[Test]
    public function it_colon_joins_the_player_ids(): void
    {
        self::assertSame(
            'players=17240%3A23133&position=RB',
            $this->queryFor(new ComparePlayersRequest(Sport::Nfl, [17240, 23133], NflPosition::RunningBack)),
        );
    }

    #[Test]
    public function it_sends_every_supported_option(): void
    {
        $request = new ComparePlayersRequest(
            Sport::Nfl,
            [17240, 23133],
            NflPosition::RunningBack,
            ComparisonRankingType::Draft,
            ComparisonDetails::All,
            expertIds: [1091, 2791],
            year: 2026,
            week: 3,
        );

        self::assertSame(
            'players=17240%3A23133&position=RB&ranking_type=draft&details=all'
            .'&experts=1091%3A2791&year=2026&week=3',
            $this->queryFor($request),
        );
    }

    /**
     * Week 0 means preseason, so it has to survive the null filtering.
     */
    #[Test]
    public function it_keeps_week_zero(): void
    {
        $request = new ComparePlayersRequest(
            Sport::Nfl,
            [17240],
            NflPosition::RunningBack,
            week: 0,
        );

        self::assertStringContainsString('week=0', $this->queryFor($request));
    }

    /**
     * Optional arguments are dropped outright rather than sent as empty
     * parameters, which the endpoint would reject.
     */
    #[Test]
    public function it_registers_no_empty_parameters(): void
    {
        self::assertSame(
            ['players' => '17240', 'position' => 'RB'],
            $this->queryParametersFor(new ComparePlayersRequest(Sport::Nfl, [17240], NflPosition::RunningBack)),
        );
    }

    #[Test]
    public function it_refuses_a_comparison_with_no_players(): void
    {
        $this->expectException(InvalidComparisonException::class);
        $this->expectExceptionMessage('Comparing players needs at least one FantasyPros player ID.');

        new ComparePlayersRequest(Sport::Nfl, [], NflPosition::RunningBack);
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_comparison(): void
    {
        $comparison = $this->recordedComparison();

        self::assertSame(Sport::Nfl, $comparison->sport);
        self::assertSame(2026, $comparison->year);
        self::assertSame(0, $comparison->week);
        self::assertSame('RB', $comparison->positionId);
        self::assertSame('draft', $comparison->rankingType);
    }

    #[Test]
    public function the_rankings_nest_scoring_format_over_player_id(): void
    {
        $comparison = $this->recordedComparison();

        self::assertSame(['STD', 'PPR', 'HALF'], array_keys($comparison->rankings));

        $ranks = $comparison->ranksFor(NflScoringType::Standard, 17240);

        self::assertCount(3, $ranks);
        self::assertSame('1091', $ranks[0]->expertId);
        self::assertSame(7, $ranks[0]->rank);
    }

    #[Test]
    public function an_unknown_player_has_no_ranks_rather_than_an_error(): void
    {
        self::assertSame([], $this->recordedComparison()->ranksFor(NflScoringType::Ppr, 999999));
    }

    #[Test]
    public function the_consensus_row_is_distinguished_from_real_experts(): void
    {
        $comparison = $this->recordedComparison();

        $ranks = $comparison->ranksFor(NflScoringType::Standard, 23133);
        $consensus = array_values(array_filter($ranks, static fn (ExpertRank $rank): bool => $rank->isConsensus()));

        self::assertCount(1, $consensus);
        self::assertSame(ExpertRank::CONSENSUS_EXPERT_ID, $consensus[0]->expertId);
        self::assertSame(2, $comparison->consensusRank(NflScoringType::Standard, 23133));
    }

    #[Test]
    public function the_consensus_rank_is_null_for_a_player_not_in_the_comparison(): void
    {
        self::assertNull($this->recordedComparison()->consensusRank(NflScoringType::Standard, 999999));
    }

    #[Test]
    public function the_player_lookup_is_keyed_by_player_id(): void
    {
        $players = $this->recordedComparison()->players;

        self::assertArrayHasKey('17240', $players);

        $barkley = $players['17240'];

        self::assertSame('Saquon Barkley', $barkley->name);
        self::assertSame('PHI', $barkley->teamId);
        self::assertSame('RB', $barkley->positionId);
        self::assertSame('http://www.fantasypros.com/nfl/players/saquon-barkley.php', $barkley->profileUrl);
    }

    #[Test]
    public function the_expert_lookup_includes_the_consensus_pseudo_expert(): void
    {
        $experts = $this->recordedComparison()->experts;

        self::assertArrayHasKey('1091', $experts);
        self::assertArrayHasKey(ExpertRank::CONSENSUS_EXPERT_ID, $experts);

        $consensus = $experts[ExpertRank::CONSENSUS_EXPERT_ID];

        self::assertSame('FantasyPros', $consensus->name);
        self::assertSame('FantasyPros ECR™', $consensus->displayName);
        // What actually sets the consensus entry apart: a blank source ID,
        // where a real expert points at their publication.
        self::assertSame('', $consensus->sourceId);
    }

    #[Test]
    public function a_real_expert_carries_their_publication_and_formats(): void
    {
        $expert = $this->recordedComparison()->experts['1091'];

        self::assertSame('Michael Hauff', $expert->name);
        self::assertSame('Michael Hauff - FF Faceoff', $expert->displayName);
        self::assertSame('1072', $expert->sourceId);
        self::assertSame('FF Faceoff', $expert->sourceName);
        self::assertSame(['STD', 'PPR', 'HALF'], $expert->scoringFormats);
    }

    private function comparison(): ComparePlayersRequest
    {
        return new ComparePlayersRequest(
            Sport::Nfl,
            [17240, 23133],
            NflPosition::RunningBack,
            ComparisonRankingType::Draft,
            ComparisonDetails::All,
            expertIds: [1091, 2791],
        );
    }

    private function recordedComparison(): PlayerComparison
    {
        $comparison = $this->dtoFrom($this->comparison(), 'nfl/compare-players');

        self::assertInstanceOf(PlayerComparison::class, $comparison);

        return $comparison;
    }
}

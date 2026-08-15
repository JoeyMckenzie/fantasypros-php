<?php

declare(strict_types=1);

use FantasyPros\Data\Api\ConsensusRankedPlayer;
use FantasyPros\Enums\ComparisonDetails;
use FantasyPros\Enums\ComparisonRankingType;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$connector = FantasyProsConnector::fromEnvironment();

$season = (int) (new DateTimeImmutable)->format('Y');

// The comparison endpoint needs real player IDs, so take the top few quarterbacks
// from the consensus rather than hardcoding IDs that go stale between seasons.
$consensus = $connector->consensusRankings(
    sport: Sport::Nfl,
    season: $season,
    position: NflPosition::Quarterback,
);

$playerIds = array_map(
    static fn (ConsensusRankedPlayer $player): int => $player->playerId,
    array_slice($consensus->players, 0, 3),
);

if ($playerIds === []) {
    exit("No quarterbacks came back from the consensus endpoint; nothing to compare.\n");
}

/**
 * GET /NFL/compare-players -- head-to-head expert ranking comparison.
 *
 * `ComparisonDetails::All` populates both the `players` and `experts` lookups;
 * without it the response carries the rank map alone.
 */
$comparison = $connector->comparePlayers(
    sport: Sport::Nfl,
    playerIds: $playerIds,
    position: NflPosition::Quarterback,
    rankingType: ComparisonRankingType::Draft,
    details: ComparisonDetails::All,
);

printf(
    "%s %s comparison -- year %d, week %d, position %s\n",
    $comparison->sport->value,
    $comparison->rankingType,
    $comparison->year,
    $comparison->week,
    $comparison->positionId,
);
printf(
    "  %d players, %d experts\n",
    count($comparison->players),
    count($comparison->experts),
);

// Quarterback ranks come back under STD. The scoring format only changes what a
// pass-catcher is worth, so FantasyPros leaves the PPR and HALF buckets empty
// for QB, K and DST -- asking for PPR here would yield no ranks at all.
$scoring = NflScoringType::Standard;

foreach ($playerIds as $playerId) {
    $player = $comparison->players[$playerId] ?? null;
    $ranks = $comparison->ranksFor($scoring, $playerId);

    printf(
        "\n  %-24.24s %-3s %-3s  consensus=%s (%d expert ranks)\n",
        $player->name ?? "player #$playerId",
        $player->positionId ?? '-',
        $player->teamId ?? '-',
        $comparison->consensusRank($scoring, $playerId) ?? '-',
        count($ranks),
    );

    foreach (array_slice($ranks, 0, 5) as $rank) {
        // The consensus is carried in the same map as the individual experts.
        $expert = $comparison->experts[$rank->expertId] ?? null;

        printf(
            "    %-28.28s rank=%d%s\n",
            $expert->displayName ?? $rank->expertId,
            $rank->rank,
            $rank->isConsensus() ? ' (consensus)' : '',
        );
    }
}

<?php

declare(strict_types=1);

use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\RankMetric;
use FantasyPros\Enums\Sport;
use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$connector = FantasyProsConnector::fromEnvironment();

$season = (int) (new DateTimeImmutable)->format('Y');

/**
 * GET /NFL/{season}/rankings -- rankings across ranking types and positions.
 *
 * `withRange` adds each player's min and max rank, `withRankStats` the average
 * and standard deviation. Without them the `ranks` map carries only the
 * consensus, and the metric lookups below return null.
 */
$rankings = $connector->rankings(
    sport: Sport::Nfl,
    season: $season,
    withRange: true,
    withRankStats: true,
);

printf(
    "%s rankings -- season %d, week %d (%d returned of %d)\n",
    $rankings->sport->value,
    $rankings->season,
    $rankings->week,
    count($rankings->players),
    $rankings->count,
);

if ($rankings->truncated()) {
    printf(
        "  truncated by the %s tier (limit %d)\n",
        $rankings->limits->tier ?? 'current',
        $rankings->limits->limit ?? 0,
    );
}

$scoring = NflScoringType::Ppr->value;

foreach (array_slice($rankings->players, 0, 10) as $player) {
    $position = $player->positionId;

    if ($position === null) {
        continue;
    }

    // Read through rank() rather than the raw map: the scoring level carries
    // undocumented keys such as ROS-STD and DYN alongside STD/PPR/HALF.
    printf(
        "  %-24.24s %-3s %-3s  ecr=%-6s min=%-6s max=%-6s stdev=%s\n",
        $player->name ?? '-',
        $position,
        $player->teamId ?? '-',
        $player->consensusRank($scoring) ?? '-',
        $player->rank(RankMetric::Minimum, $scoring, $position) ?? '-',
        $player->rank(RankMetric::Maximum, $scoring, $position) ?? '-',
        $player->rank(RankMetric::StandardDeviation, $scoring, $position) ?? '-',
    );
}

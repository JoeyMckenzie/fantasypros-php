<?php

declare(strict_types=1);

use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$connector = FantasyProsConnector::fromEnvironment();

$season = (int) (new DateTimeImmutable)->format('Y');

/**
 * GET /NFL/{season}/rankings/experts: profiles of the ranking experts.
 *
 * `position` is optional here, unlike on the consensus-rankings route.
 * `withOverallAccuracy` adds the `ALL` row to each accuracy map.
 *
 * `rankingType` is deliberately left off. It narrows to the experts who
 * published that specific ranking set, and a season that has not started yet
 * has none of them. Every NflRankingType returns an empty directory for the
 * current season, while 2025 answers WW and WAIVER. Pass it only when you're
 * asking about a season whose rankings exist.
 */
$directory = $connector->experts(
    sport: Sport::Nfl,
    season: $season,
    position: NflPosition::Quarterback,
    scoring: NflScoringType::Ppr,
    withOverallAccuracy: true,
);

printf(
    "%s experts for season %d, week %d (%d returned of %d)\n",
    $directory->sport->value,
    $directory->season,
    $directory->week,
    count($directory->experts),
    $directory->count,
);
printf(
    "  accuracy seasons: draft %s, weekly %s, last weekly %s\n",
    $directory->draftAccuracySeason ?? '-',
    $directory->weeklyAccuracySeason ?? '-',
    $directory->lastWeeklyAccuracySeason ?? '-',
);

if ($directory->truncated()) {
    printf(
        "  truncated by the %s tier (limit %d)\n",
        $directory->limits->tier ?? 'current',
        $directory->limits->limit ?? 0,
    );
}

foreach (array_slice($directory->experts, 0, 10) as $expert) {
    printf(
        "  #%-5d %-28.28s %-22.22s overall draft accuracy=%s%s\n",
        $expert->id,
        $expert->name,
        $expert->source,
        $expert->draftAccuracy[NflPosition::All->value] ?? '-',
        // `defaults` maps a position to whether this expert is a default for it.
        $expert->isDefaultFor(NflPosition::Quarterback->value) ? ' (default QB)' : '',
    );
}

<?php

declare(strict_types=1);

use FantasyPros\Data\Api\PlayerPoints;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$connector = FantasyProsConnector::fromEnvironment();

// The completed season rather than the current one: player-points reports what
// was actually scored, so a season yet to start has nothing to report.
$season = (int) (new DateTimeImmutable)->format('Y') - 1;

/**
 * GET /NFL/{season}/player-points -- points actually scored, week by week.
 *
 * The counterpart to projections. Every parameter is optional; without a range
 * the API counts the whole regular season.
 */
$points = $connector->playerPoints(
    sport: Sport::Nfl,
    season: $season,
    startWeek: 1,
    endWeek: 6,
    position: NflPosition::Quarterback,
    scoring: NflScoringType::Ppr,
);

printf(
    "NFL %d quarterbacks, weeks 1-6, %s scoring\n",
    $points->season,
    $points->scoring ?? 'default',
);

// A player who did not appear in the requested range comes back as an identity
// block alone -- no games, points, average or weeks -- despite the spec marking
// all four required. They read as a scoreless line rather than throwing.
$played = array_values(array_filter(
    $points->players,
    static fn (PlayerPoints $player): bool => $player->games > 0,
));

printf(
    "  %d players returned, %d of whom actually played\n",
    count($points->players),
    count($played),
);

foreach (array_slice($played, 0, 10) as $player) {
    printf(
        "  %-24.24s %-3s  games=%-3d total=%-7s avg=%-6s best=%s\n",
        $player->name ?? '-',
        $player->teamId ?? '-',
        $player->games,
        $player->points,
        $player->average,
        $player->bestWeek() ?? '-',
    );
}

// The weekly breakdown is keyed by week number, and only the weeks a player
// actually played appear -- so it is sparser than the range asked for.
$leader = $played[0] ?? null;

if ($leader instanceof PlayerPoints) {
    printf("\n  %s week by week:\n", $leader->name ?? 'the leader');

    foreach ($leader->weeks as $week => $scored) {
        printf("    week %-3d %s\n", $week, $scored);
    }

    // Weeks outside the requested range simply answer null.
    printf("    week 17  %s (outside the requested range)\n", $leader->inWeek(17) ?? '-');
}

/**
 * `min=true` trades the name, position and team for a smaller response, leaving
 * the ID and the numbers.
 */
$minimal = $connector->playerPoints(
    sport: Sport::Nfl,
    season: $season,
    startWeek: 1,
    endWeek: 6,
    position: NflPosition::Quarterback,
    scoring: NflScoringType::Ppr,
    minimal: true,
);

$first = $minimal->players[0] ?? null;

if ($first instanceof PlayerPoints) {
    printf(
        "\n  minimal: id=%d name=%s team=%s points=%s\n",
        $first->id,
        $first->name ?? 'dropped',
        $first->teamId ?? 'dropped',
        $first->points,
    );
}

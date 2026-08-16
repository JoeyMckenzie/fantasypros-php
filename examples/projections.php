<?php

declare(strict_types=1);

use FantasyPros\Data\Api\ProjectedPlayer;
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
 * GET /NFL/{season}/projections: projected stat lines for a position.
 *
 * `position` is required, as on consensus-rankings. Week 0 would ask for
 * preseason projections rather than meaning "no week given".
 */
$projections = $connector->projections(
    sport: Sport::Nfl,
    season: $season,
    position: NflPosition::RunningBack,
    week: 1,
);

printf(
    "NFL %d week %d: %s projections, filed under %s\n",
    $projections->season,
    $projections->week,
    $projections->positions,
    $projections->scoring ?? 'default scoring',
);
printf("  %d players returned of %d\n", count($projections->players), $projections->count);

if ($projections->truncated()) {
    printf(
        "  truncated by the %s tier (limit %d)\n",
        $projections->limits->tier ?? 'current',
        $projections->limits->limit ?? 0,
    );
}

// Every position carries all three points keys, so the scoring format can be
// chosen at read time rather than at request time. For a running back they
// genuinely differ; for a quarterback or a defence all three hold one number.
foreach (array_slice($projections->players, 0, 10) as $player) {
    printf(
        "  %-24.24s %-3s  std=%-6s ppr=%-6s half=%-6s  rush=%-6s rec=%s\n",
        $player->name,
        $player->teamId,
        $player->points() ?? '-',
        $player->points(NflScoringType::Ppr) ?? '-',
        $player->points(NflScoringType::Half) ?? '-',
        $player->stat('rush_yds') ?? '-',
        $player->stat('rec_yds') ?? '-',
    );
}

/**
 * The stat line is a map because its keys change with the position. A defence
 * shares only the three points keys with the running backs above. The spec's
 * own per-position schemas get this wrong, naming `def_pa_a` through `def_pa_g`
 * where the live route returns `def_pa` and `def_tyda`.
 */
$defences = $connector->projections(
    sport: Sport::Nfl,
    season: $season,
    position: NflPosition::Defense,
    week: 1,
);

echo "\n  defences, whose stat keys are entirely different:\n";

foreach (array_slice($defences->players, 0, 5) as $defence) {
    printf(
        "  %-24.24s %-3s  pts=%-6s sacks=%-5s ints=%-5s pa=%-6s yds allowed=%s\n",
        $defence->name,
        $defence->teamId,
        $defence->points() ?? '-',
        $defence->stat('def_sack') ?? '-',
        $defence->stat('def_int') ?? '-',
        $defence->stat('def_pa') ?? '-',
        $defence->stat('def_tyda') ?? '-',
    );
}

$first = $defences->players[0] ?? null;

if ($first instanceof ProjectedPlayer) {
    printf("\n  a defence's full stat key set: %s\n", implode(', ', array_keys($first->stats)));
}

/**
 * Rest-of-season projections. Worth knowing before relying on this: the API
 * accepts `ros`, echoes it back, and then answers with a literal `null` for
 * `players` once the season has no games left. No error, just an empty set.
 */
$restOfSeason = $connector->projections(
    sport: Sport::Nfl,
    season: $season,
    position: NflPosition::RunningBack,
    restOfSeason: true,
);

printf(
    "\n  rest of season: ros=%s, %d players, count %d\n",
    $restOfSeason->restOfSeason ? 'true' : 'false',
    count($restOfSeason->players),
    $restOfSeason->count,
);

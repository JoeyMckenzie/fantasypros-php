<?php

declare(strict_types=1);

use FantasyPros\Enums\ExternalIdSource;
use FantasyPros\Enums\Sport;
use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$connector = FantasyProsConnector::fromEnvironment();

/**
 * GET /NFL/players: rosters and player metadata.
 *
 * Folds in the Yahoo and ESPN player IDs and asks for each player's positional
 * rank, so the response carries more than the identity block.
 */
$players = $connector->players(
    sport: Sport::Nfl,
    externalIds: [ExternalIdSource::Yahoo, ExternalIdSource::Espn],
    withPositionRank: true,
);

printf(
    "%s players for season %d, week %d (%d returned of %d)\n",
    $players->sport->value,
    $players->season,
    $players->week,
    count($players->players),
    $players->count,
);

// The free tier truncates to `limit` while still reporting the full count.
if ($players->truncated()) {
    printf(
        "  truncated by the %s tier (limit %d)\n",
        $players->limits->tier ?? 'current',
        $players->limits->limit ?? 0,
    );
}

foreach (array_slice($players->players, 0, 10) as $player) {
    printf(
        "  %-24.24s %-3s %-3s  ecr=%-4s adp=%-4s age=%s\n",
        $player->name,
        $player->positionId,
        $player->teamId,
        $player->ecrRank ?? '-',
        $player->adpRank ?? '-',
        $player->age ?? '-',
    );
}

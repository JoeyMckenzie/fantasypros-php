<?php

declare(strict_types=1);

use FantasyPros\Enums\ExpertsDetail;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$connector = FantasyProsConnector::fromEnvironment();

$season = (int) (new DateTimeImmutable)->format('Y');

/**
 * GET /NFL/{season}/consensus-rankings: the expert consensus for one position.
 *
 * Unlike the experts route, `position` is required here. `ExpertsDetail::Show`
 * asks the endpoint to name the experts behind the consensus rather than just
 * counting them.
 */
$consensus = $connector->consensusRankings(
    sport: Sport::Nfl,
    season: $season,
    position: NflPosition::Quarterback,
    rankingType: NflRankingType::Draft,
    scoring: NflScoringType::Ppr,
    experts: ExpertsDetail::Show,
);

printf(
    "%s: %s (%s, %s) year %d week %d\n",
    $consensus->sport->value,
    $consensus->label,
    $consensus->rankingType,
    $consensus->scoring ?? 'default scoring',
    $consensus->year,
    $consensus->week,
);
printf(
    "  %d players returned of %d, from %d experts, last updated %s\n",
    count($consensus->players),
    $consensus->count,
    $consensus->totalExperts,
    $consensus->lastUpdated ?? 'unknown',
);

if ($consensus->truncated()) {
    printf(
        "  truncated by the %s tier (limit %d)\n",
        $consensus->limits->tier ?? 'current',
        $consensus->limits->limit ?? 0,
    );
}

foreach (array_slice($consensus->players, 0, 10) as $player) {
    printf(
        "  %-24.24s %-3s  ecr=%-4s spread=%-5s grade=%-3s bye=%s\n",
        $player->name,
        $player->teamId ?? '-',
        $player->rankEcr ?? '-',
        $player->rankSpread() ?? '-',
        $player->startSitGrade ?? '-',
        $player->byeWeek ?? '-',
    );
}

foreach (array_slice($consensus->experts, 0, 5) as $expert) {
    printf(
        "  expert #%d %-24.24s published %s\n",
        $expert->id,
        $expert->name ?? '-',
        $expert->publishedAt ?? '-',
    );
}

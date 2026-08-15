<?php

declare(strict_types=1);

use FantasyPros\Enums\Sport;
use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$connector = FantasyProsConnector::fromEnvironment();

/**
 * GET /NFL/injuries -- injury statuses and, for the NFL, the practice report.
 *
 * `includeProbabilities` widens the report to players who appear on the
 * practice report without carrying an injury status. Narrowing by `teamIds`
 * keeps the output small; drop it to pull the league.
 */
$report = $connector->injuries(
    sport: Sport::Nfl,
    teamIds: ['SF', 'KC'],
    includeProbabilities: true,
);

printf(
    "%s injuries -- %d returned of %d\n",
    $report->sport->value,
    count($report->injuries),
    $report->count,
);

if ($report->truncated()) {
    printf(
        "  truncated by the %s tier (limit %d)\n",
        $report->limits->tier ?? 'current',
        $report->limits->limit ?? 0,
    );
}

foreach (array_slice($report->injuries, 0, 10) as $injury) {
    // status() resolves the wire string to NflInjuryStatus, or null when the
    // API sends something outside the known vocabulary.
    printf(
        "  %-24.24s %-3s %-3s  %-26.26s %-16.16s play=%s\n",
        $injury->name,
        $injury->positionId ?? '-',
        $injury->teamId ?? '-',
        $injury->status()->name ?? $injury->statusShort,
        // Often empty for players on PUP or IR, where the status is the story.
        $injury->injuryType === '' ? '-' : $injury->injuryType,
        $injury->probabilityOfPlaying ?? '-',
    );

    // The practice report is NFL-only and often empty outside the season.
    if ($injury->firstPracticeSubmitted) {
        printf(
            "      practice: %s / %s / %s (%s)\n",
            $injury->firstPractice ?? '-',
            $injury->secondPractice ?? '-',
            $injury->thirdPractice ?? '-',
            $injury->practiceReportInjuryType ?? '-',
        );
    }
}

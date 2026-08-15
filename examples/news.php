<?php

declare(strict_types=1);

use FantasyPros\Enums\NewsCategory;
use FantasyPros\Enums\NewsOrder;
use FantasyPros\Enums\Sport;
use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$connector = FantasyProsConnector::fromEnvironment();

/**
 * GET /NFL/news -- the player news feed.
 *
 * The endpoint caps `limit` at 100 and defaults to 25 when omitted. Pass
 * `playerId` to narrow the feed to a single player.
 */
$feed = $connector->news(
    sport: Sport::Nfl,
    limit: 10,
    category: NewsCategory::Injury,
    orderBy: NewsOrder::Updated,
);

printf("%s -- %s\n", $feed->sport->value, $feed->title);
printf("  %s\n", $feed->description);
printf("  %d returned of %d\n", count($feed->items), $feed->count);

foreach ($feed->items as $item) {
    printf(
        "\n  [%s] %s\n",
        $item->createdFormatted,
        $item->title,
    );
    printf(
        "    player=%d team=%s by %-20.20s categories=%s\n",
        $item->playerId,
        $item->teamId,
        $item->author,
        $item->categories === [] ? '-' : implode(', ', $item->categories),
    );

    if ($item->impact !== '') {
        printf("    impact: %s\n", mb_substr($item->impact, 0, 160));
    }

    printf("    %s\n", $item->link);
}

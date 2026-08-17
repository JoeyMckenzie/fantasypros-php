# FantasyPros PHP

A PHP client for the [FantasyPros API](https://api.fantasypros.com): players, rankings,
projections, news and injuries. Built on [Saloon](https://docs.saloon.dev).

You call methods on a connector and get back typed objects. No `Request` classes to
construct, no `->dto()`, no `assert()` to convince your static analyser:

```php
$connector = FantasyProsConnector::fromEnvironment();

$players = $connector->players(sport: Sport::Nfl, withPositionRank: true);
```

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Getting an API key](#getting-an-api-key)
- [Endpoints](#endpoints)
  - [Players](#players)
  - [Rankings](#rankings)
  - [Consensus rankings](#consensus-rankings)
  - [Experts](#experts)
  - [Projections](#projections)
  - [Player points](#player-points)
  - [Injuries](#injuries)
  - [News](#news)
  - [Compare players](#compare-players)
- [Handling failures](#handling-failures)
- [Truncated responses](#truncated-responses)
- [Quirks worth knowing about](#quirks-worth-knowing-about)
- [Contributing](#contributing)
- [License](#license)

## Requirements

PHP 8.5 or newer.

## Installation

```bash
composer require fantasyphp/fantasypros-php
```

## Getting an API key

Request one at [secure.fantasypros.com/api-keys/request](https://secure.fantasypros.com/api-keys/request/).

The connector reads `FANTASYPROS_API_KEY` from the environment:

```php
use FantasyPros\FantasyProsConnector;

$connector = FantasyProsConnector::fromEnvironment();
```

If you'd rather pass it yourself, hand it to the constructor:

```php
$connector = new FantasyProsConnector($apiKey);
```

Either way a blank key throws `MissingApiKeyException` right there, so a misconfigured
environment fails where you made the mistake rather than on your first request.

## Endpoints

Every example below is a trimmed version of a runnable script in [`examples/`](examples).
Those run against the live API, so if you want to poke at a response shape, start there.

### Players

Rosters and player metadata. `externalIds` folds in IDs from other sites.

```php
$players = $connector->players(
    sport: Sport::Nfl,
    externalIds: [ExternalIdSource::Yahoo, ExternalIdSource::Espn],
    withPositionRank: true,
);

foreach ($players->players as $player) {
    printf("%s (%s, %s)\n", $player->name, $player->positionId, $player->teamId);
}
```

### Rankings

Every ranking a player holds, nested metric over scoring over position. Read them through
`rank()` rather than indexing into `$ranks` yourself.

```php
$rankings = $connector->rankings(
    sport: Sport::Nfl,
    season: 2025,
    withRange: true,
    withRankStats: true,
);

foreach ($rankings->players as $player) {
    printf("%s %s\n", $player->name, $player->consensusRank('STD') ?? '-');
}
```

### Consensus rankings

One flat ranking for the position you ask for. `position` is required here.
`ExpertsDetail::Show` names the experts behind the consensus instead of just counting them.

```php
$consensus = $connector->consensusRankings(
    sport: Sport::Nfl,
    season: 2025,
    position: NflPosition::Quarterback,
    rankingType: NflRankingType::Draft,
    scoring: NflScoringType::Ppr,
    experts: ExpertsDetail::Show,
);

foreach ($consensus->players as $player) {
    printf("%s ecr=%s spread=%s\n", $player->name, $player->rankEcr, $player->rankSpread());
}
```

### Experts

Profiles of the people behind the rankings. `position` is optional here, unlike on
consensus rankings.

```php
$directory = $connector->experts(
    sport: Sport::Nfl,
    season: 2025,
    position: NflPosition::Quarterback,
    scoring: NflScoringType::Ppr,
    withOverallAccuracy: true,
);
```

### Projections

Projected stat lines for a position. The stat line is a map because its keys change with
the position, so read it with `stat()` and take the points with `points()`.

```php
$projections = $connector->projections(
    sport: Sport::Nfl,
    season: 2026,
    position: NflPosition::RunningBack,
    week: 1,
);

foreach ($projections->players as $player) {
    printf(
        "%s std=%s ppr=%s rush=%s\n",
        $player->name,
        $player->points(),
        $player->points(NflScoringType::Ppr),
        $player->stat('rush_yds'),
    );
}
```

Pass `restOfSeason: true` for rest-of-season numbers instead of a single week.

### Player points

What players actually scored, week by week. The counterpart to projections. Every
parameter is optional; without a range the API counts the whole regular season.

```php
$points = $connector->playerPoints(
    sport: Sport::Nfl,
    season: 2025,
    startWeek: 1,
    endWeek: 6,
    position: NflPosition::Quarterback,
    scoring: NflScoringType::Ppr,
);

foreach ($points->players as $player) {
    printf("%s %s total, best week %s\n", $player->name, $player->points, $player->bestWeek());
}
```

`$player->weeks` is keyed by week number and only holds weeks the player appeared in, so
it's sparser than the range you asked for. Use `inWeek()` rather than indexing blind.

### Injuries

Injury statuses, plus the weekly practice report for the NFL.

```php
$report = $connector->injuries(
    sport: Sport::Nfl,
    teamIds: ['SF', 'KC'],
    includeProbabilities: true,
);

foreach ($report->injuries as $injury) {
    printf("%s %s (%s)\n", $injury->name, $injury->status, $injury->injuryType);
}
```

### News

The player news feed.

```php
$feed = $connector->news(
    sport: Sport::Nfl,
    limit: 10,
    category: NewsCategory::Injury,
    orderBy: NewsOrder::Updated,
);

foreach ($feed->items as $item) {
    printf("%s\n  %s\n", $item->title, $item->link);
}
```

### Compare players

Head-to-head expert rankings for two or more players.

```php
$comparison = $connector->comparePlayers(
    sport: Sport::Nfl,
    playerIds: [19275, 15600],
    position: NflPosition::Quarterback,
    rankingType: ComparisonRankingType::Draft,
    details: ComparisonDetails::All,
);

$ranks = $comparison->ranksFor(NflScoringType::Standard, 19275);
```

## Handling failures

Failures come back as FantasyPros types, never raw Saloon ones. Catch the
`FantasyProsRequestException` interface for all of them, or a specific class:

```php
use FantasyPros\Exceptions\AuthenticationException;
use FantasyPros\Exceptions\FantasyProsRequestException;
use FantasyPros\Exceptions\RateLimitException;

try {
    $players = $connector->players(sport: Sport::Nfl);
} catch (RateLimitException $e) {
    // Quota spent. The connector already retried three times with backoff.
} catch (AuthenticationException $e) {
    // The key was refused.
} catch (FantasyProsRequestException $e) {
    // Anything else the API returned.
}
```

The connector retries 429s and 5xx responses three times with exponential backoff before
giving up. A refused key isn't retried, since it fails the same however often you ask.

## Truncated responses

The free tier quietly returns fewer players than it says exist. It isn't an error and
nothing throws, so check for it:

```php
if ($players->truncated()) {
    printf("got %d of %d, capped by the %s tier\n",
        count($players->players),
        $players->count,
        $players->limits->tier,
    );
}
```

## Quirks worth knowing about

These cost real debugging time, so they're worth stating up front.

**Scoring format only matters for pass-catchers.** On the ranking routes, QB, K and DST
ranks come back under `STD` with the `PPR` and `HALF` buckets empty. Ask for PPR ranks for
a quarterback and you'll get an empty array rather than an error. Projections don't behave
this way: there every position carries all three points keys, and for a QB or DST all
three hold the same number.

**Narrowing experts by ranking type needs a season that already has rankings.** Passing
`rankingType` to `experts()` filters to the people who published that set. For a season
that hasn't started, that's nobody, and you get an empty directory back with no error.

**Bad parameters don't fail.** The API ignores values it doesn't understand and answers
200 with defaults. A typo in a season or position gives you a plausible-looking response
for something you didn't ask for.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the toolchain, the quality gate and how
fixtures work.

## License

MIT. See [LICENSE](LICENSE).

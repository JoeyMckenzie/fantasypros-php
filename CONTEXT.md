# Project Context

## What this is

`joeymckenzie/fantasypros-php` — a [Saloon v4](https://docs.saloon.dev) based PHP client for
the [FantasyPros public API](https://api.fantasypros.com/public/v2/json), published on
Packagist. There is no FantasyPros PHP SDK otherwise, which is the reason this one is
public rather than private.

The package is a library and nothing else: no CLI, no server, no application code. The
consumer that drives it — a Laravel Zero CLI for weekly fantasy analysis — lives in its own
repository and depends on this package like any other user would. That separation is the
point: whatever the CLI needs, it gets through this package's public surface, so anyone
else's consumer is equally well served.

An MCP server was originally planned as a second deliverable inside this repo. That is no
longer part of this project; those tickets are archived and the CLI took its place.

```
composer.json         # PSR-4: FantasyPros\ => src/
src/
  FantasyProsConnector.php
  Requests/           # one Request class per endpoint
  Data/               # readonly response DTOs
  Enums/              # query-parameter and response vocabularies
  Exceptions/
tests/                # PHPUnit tests + recorded Saloon fixtures
  Fixtures/Saloon/    # real API payloads, committed, replayed offline
docs/                 # OpenAPI spec + reference material
.backlog/             # Backlog.md task tracking (task prefix: FANTASY)
```

## API notes (from the OpenAPI spec)

- Base URL: `https://api.fantasypros.com/public/v2/json`
- Auth: `x-api-key` header (key requested from FantasyPros; supplied via the
  `FANTASYPROS_API_KEY` env var, never committed)
- The API is multi-sport (NFL/MLB/NBA/NHL) with a `{sport}` path parameter and
  discriminated response schemas per sport. **Scope is NFL-first**, but sport is modeled as
  an enum so other sports can be added without restructuring. Response DTOs that differ
  structurally per sport (comparisons, injuries, rankings) are NFL-shaped on purpose; a
  second sport gets its own DTO rather than a nullable middle layer.
- Endpoints covered or planned:
  - `GET /{sport}/players` — rosters + metadata
  - `GET /{sport}/news` — player news
  - `GET /{sport}/injuries` — injury reports (supports year/week)
  - `GET /{sport}/compare-players` — head-to-head ranking comparison
  - `GET /{sport}/{season}/rankings` — expert rankings
  - `GET /{sport}/{season}/consensus-rankings` — ECR by position/scoring
  - `GET /{sport}/{season}/rankings/experts` — expert profiles/accuracy
  - `GET /nfl/{season}/projections` — weekly/preseason/ROS projections
  - `GET /nfl/{season}/player-points` — actual fantasy points scored
- Out of scope: MLB lineups, MLB/NBA projections.

**The spec is not reliable, and the fixtures are.** Across the endpoints built so far the
live payloads have diverged from `docs/fantasypros-open-api-spec-v2.yml` in a dozen
documented ways — fields the spec marks required arriving null, keys named differently
(`expert_names` vs `expert_name`, `default` vs `defaults`), enum values outside the
declared set, undocumented nesting, and a parameter whose own description contradicts its
schema. Record a real fixture and read it before modelling any DTO.

## Design

- `FantasyProsConnector` — base URL, `x-api-key` header auth, JSON defaults, retry and
  timeout config. Constructed with a key, or via `fromEnvironment()`.
- One `Request` class per endpoint under `Requests/`, with constructor arguments for path
  and query parameters. Unset options are filtered out rather than sent empty.
- Responses map to `final readonly` DTOs under `Data/` via `createDtoFromResponse()`.
  Item-level DTOs implement `ApiDataContract` (`fromPayload(Payload): self`); envelope DTOs
  take a whole `Response` instead.
- `Payload` centralises the API's loose scalar typing — it returns numeric strings as ints
  or floats, and its map readers reject an unreadable entry rather than dropping it.
- Typed exceptions for auth failures, validation errors (400 returns
  `{message, parameter, valid_format}`), and rate limiting.
- The internal layering is enforced by Deptrac (`deptrac.php`), outermost first:
  `connector` may reach anything; `requests` may reach `data`, `enums`, `exceptions`;
  `data` may reach `enums` and `exceptions`; `enums` and `exceptions` are leaves. So DTOs
  can never reach back up into the requests that return them, and nothing depends on the
  connector — requests and DTOs stay usable with any Saloon connector.
- Tests use PHPUnit + Saloon's `MockClient` against **recorded** fixtures, in two modes
  selected by `FANTASY_FIXTURES`:
  - `composer test` (default) — fully offline. `MockConfig::throwOnMissingFixtures()` means
    a missing fixture is a hard failure, never a silent call to the live API.
  - `composer test:record` — loads `.env`, requires a real `FANTASYPROS_API_KEY`, and lets
    Saloon record any missing fixture from the live API. `composer fixtures:refresh` wipes
    and re-records everything.
  Fixtures are committed, so the offline suite exercises real payload shapes. A recorded
  fixture stores only the response status, headers and body — Saloon never writes request
  headers, so the API key cannot leak into one, and `FixtureSafetyTest` checks that.

## Conventions

- PHP 8.4+, strict types everywhere.
- Tests declare themselves with the `#[Test]` attribute, not a `test_` method prefix.
  Method names read as sentences: `it_authenticates_with_the_api_key_header()`.
- Enum cases are separated by blank lines.
- Tooling, all driven by composer scripts: PHPUnit (`test`, `test:record`), Infection
  mutation testing (`test:mutate`, `minMsi` in `infection.json5`), PHPStan at `level: max`
  with 100% type coverage (`lint`), Laravel Pint (`fmt`), Rector (`refactor`), and Deptrac
  for the layering above. Run `fmt` before `refactor` before `lint` — Pint and Rector fight
  in the other order.
- Work is tracked in Backlog.md (`.backlog/` directory). File a task before starting a
  chunk of work; keep acceptance criteria testable.

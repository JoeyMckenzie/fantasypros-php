# Project Context

## What this is

A personal-use PHP project for pulling fantasy football data from the
[FantasyPros public API](https://api.fantasypros.com/public/v2/json) and exposing it to
Claude via an MCP server, so Claude can help analyze fantasy teams and recommend weekly
lineups.

Two deliverables, built in order:

1. **SDK** (`src/Sdk`, namespace `Fantasy\Sdk`) — a [Saloon v3](https://docs.saloon.dev)
   based client wrapping the FantasyPros API described in
   `docs/fantasypros-open-api-spec-v2.yml`.
2. **MCP server** (`src/Mcp`, namespace `Fantasy\Mcp`) — built on the official
   [MCP PHP SDK](https://github.com/modelcontextprotocol/php-sdk) (`mcp/sdk`), consuming
   the SDK and exposing tools/prompts over stdio for Claude Code / Claude Desktop.

## Repository layout decision

**Single Composer package, two namespaces.** A monorepo (`apps/` + `packages/`) was
considered and rejected for now: this is a personal project with one consumer of the SDK,
so path repositories, split composer.json files, and cross-package versioning add
ceremony with no payoff. The namespace boundary (`Fantasy\Sdk` must never depend on
`Fantasy\Mcp`; the MCP layer only consumes the SDK's public surface) gives us the same
separation, and extracting `src/Sdk` into its own package later is mechanical if it ever
needs to be published independently. The empty `apps/` and `packages/` directories are
vestigial and can be deleted.

```
composer.json         # single package, PSR-4: Fantasy\ => src/
src/
  Sdk/                # Saloon connector, requests, DTOs, enums
  Mcp/                # MCP server, tools, prompts
tests/                # Pest tests, Saloon MockClient fixtures
docs/                 # OpenAPI spec + reference material
.backlog/             # Backlog.md task tracking (task prefix: FANTASY)
bin/                  # MCP server entrypoint (later phase)
```

## API notes (from the OpenAPI spec)

- Base URL: `https://api.fantasypros.com/public/v2/json`
- Auth: `x-api-key` header (key requested from FantasyPros; supplied via the
  `FANTASYPROS_API_KEY` env var, never committed)
- The API is multi-sport (NFL/MLB/NBA/NHL) with a `{sport}` path parameter and
  discriminated response schemas per sport. **Scope is NFL-first** — that's the actual
  use case — but sport is modeled as an enum so other sports can be added without
  restructuring.
- Endpoints in scope for the SDK (NFL-relevant set):
  - `GET /{sport}/players` — rosters + metadata
  - `GET /{sport}/news` — player news
  - `GET /{sport}/injuries` — injury reports (supports year/week)
  - `GET /{sport}/compare-players` — head-to-head ranking comparison
  - `GET /{sport}/{season}/rankings` — expert rankings
  - `GET /{sport}/{season}/consensus-rankings` — ECR by position/scoring
  - `GET /{sport}/{season}/rankings/experts` — expert profiles/accuracy
  - `GET /nfl/{season}/projections` — weekly/preseason/ROS projections
  - `GET /nfl/{season}/player-points` — actual fantasy points scored
- Out of scope: MLB lineups, MLB/NBA projections (add later only if wanted).

## SDK design (Saloon)

- `FantasyProsConnector` — base URL, `x-api-key` header auth, JSON body defaults,
  sensible retry/timeout config.
- One `Request` class per endpoint under `Sdk/Requests/`, with constructor arguments for
  path/query parameters. Query enums (`Sport`, `Position`, `Scoring`, `RankingType`,
  `NewsCategory`, …) live under `Sdk/Enums/`.
- Responses map to readonly DTOs under `Sdk/Data/` via `createDtoFromResponse()` —
  starting with the entities the MCP layer needs (players, rankings, projections,
  injuries, news items). Fields we don't care about can stay unmapped.
- Typed exceptions for auth failures (401/403), validation errors (400 returns
  `{message, parameter, valid_format}`), and rate limiting.
- Tests use Pest + Saloon's `MockClient` with JSON fixtures shaped by the spec examples —
  no live API calls in the test suite.

## MCP design (second phase, tickets filed but not yet planned in detail)

- Stdio server via `mcp/sdk`, entrypoint at `bin/fantasy-mcp`.
- Tools wrap SDK calls: get rankings, get projections, get injuries/news, compare
  players, get player points.
- A lineup-analysis prompt that guides Claude to combine those tools for weekly
  start/sit decisions.

## Conventions

- PHP 8.3+, strict types everywhere.
- Tooling: Pest (tests), PHPStan (max level as practical), Laravel Pint (style), driven
  by composer scripts (`composer test`, `composer lint`, `composer analyse`).
- Work is tracked in Backlog.md (`.backlog/` directory, MCP or `backlog` CLI). File a
  task before starting a chunk of work; keep acceptance criteria testable.

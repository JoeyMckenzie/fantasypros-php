---
id: FANTASY-2
title: 'Saloon connector, API-key auth, and core enums'
status: Done
assignee: []
created_date: '2026-08-14 06:16'
updated_date: '2026-08-14 18:11'
labels:
  - sdk
dependencies:
  - FANTASY-1
modified_files:
  - src/Sdk/FantasyProsConnector.php
  - src/Sdk/Exceptions/MissingApiKeyException.php
  - src/Sdk/Enums/Sport.php
  - src/Sdk/Enums/NflPosition.php
  - src/Sdk/Enums/NflRankingType.php
  - src/Sdk/Enums/NflScoringType.php
  - tests/Sdk/FantasyProsConnectorTest.php
  - tests/Sdk/EnumsMatchSpecTest.php
  - tests/Sdk/Doubles/StubRequest.php
  - tests/TestCase.php
  - composer.json
priority: high
ordinal: 2000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
FantasyProsConnector with base URL https://api.fantasypros.com/public/v2/json, x-api-key header auth (key via constructor, resolved from FANTASYPROS_API_KEY env in a small factory), JSON defaults, timeout + retry config. Enums under Sdk/Enums: Sport, Position, Scoring, RankingType, NewsCategory.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Connector sends x-api-key header (asserted via Saloon MockClient test)
- [x] #2 Enums cover the values enumerated in docs/fantasypros-open-api-spec-v2.yml
- [x] #3 Missing API key fails with a clear exception, not a 401 at request time
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
## Steps

1. `src/Sdk/Exceptions/MissingApiKeyException.php` -- thrown at construction time, not request time (AC #3).

2. `src/Sdk/FantasyProsConnector.php`
   - `resolveBaseUrl(): 'https://api.fantasypros.com/public/v2/json'`
   - `x-api-key` via `defaultAuth(): HeaderAuthenticator($this->apiKey, 'x-api-key')`
   - traits `AcceptsJson`, `AlwaysThrowOnErrors`, `HasTimeout` (`$connectTimeout`, `$requestTimeout`)
   - `$tries = 3`, retrying connection failures only -- must NOT retry 4xx, since a bad key or bad parameter is not transient
   - constructor rejects an empty/whitespace key with `MissingApiKeyException`
   - verify: MockClient test asserts the `x-api-key` header on the outgoing PSR request (AC #1)

3. `src/Sdk/FantasyPros.php` -- thin factory, `fromEnvironment()` reads `FANTASYPROS_API_KEY`, throws `MissingApiKeyException` naming the env var when absent.
   - verify: test asserts the exception + message with the env var unset (AC #3)

4. `src/Sdk/Enums/` -- backed string enums: `Sport` (NFL, MLB, NBA, NHL, PGA, NCAAF), `NflPosition`, `NflRankingType`, `NflScoringType` (STD, PPR, HALF).
   - `Sport::value` is lowercased for the `{sport}` path segment (spec enumerates `NFL` but the code samples call `/nfl/...`); a `pathSegment()` accessor keeps that in one place
   - `NflRankingType`: the spec lists `PRO` and `PROSPECT` twice in `NFLRankingTypes` -- PHP enums cannot duplicate cases, so each appears once
   - verify: test round-trips every case against the values in docs/fantasypros-open-api-spec-v2.yml (AC #2)

## Scope note

The ticket lists `Position`, `Scoring`, `RankingType`, `NewsCategory` as flat enums. Two deviations, flagged to the user:

- The spec defines positions / ranking types / scoring per sport (`NFLPositions`, `MLBPositions`, ...) with no shared value set. Since scope is NFL-first, these ship as `Nfl*`-prefixed enums; MLB/NBA/NHL variants get added when those sports do, rather than inventing a lowest-common-denominator enum now.
- `NewsCategory` (injury, recap, transaction, rumor, breaking) is deferred to FANTASY-4, where the news request that consumes it lands. Landing an unused enum here would be speculative.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Env-var resolution order is `$_SERVER` then `$_ENV` then `getenv()`, and that precedence is now pinned by a test -- it matters because vlucas/phpdotenv's `createImmutable` populates `$_SERVER`/`$_ENV` but deliberately does not call `putenv`, so a getenv-only lookup would not see `.env` at all.

The suite is bootstrapped by `tests/bootstrap.php` and a shared `Fantasy\Tests\TestCase` that snapshots and restores all three env sources per test; without that, a test poking at the key leaks into the next one.

One live-behaviour caveat worth carrying into FANTASY-3: everything here is verified against MockClient, so the x-api-key header is proven to be *sent*, not proven to be *accepted*. The first `composer test:record` run against the real API is what will confirm the key actually authenticates.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
All three acceptance criteria met. 30 tests green offline, PHPStan level max clean, Rector and Pint clean, Deptrac 0 violations, Infection MSI 100%.

**AC #1 -- x-api-key header.** `FantasyProsConnector` authenticates via `defaultAuth(): HeaderAuthenticator($apiKey, 'x-api-key')`, asserted through `MockClient::getLastPendingRequest()`.

**AC #2 -- enums match the spec.** Rather than hand-copy the value lists, `EnumsMatchSpecTest` parses `docs/fantasypros-open-api-spec-v2.yml` and diffs each enum's cases against the corresponding schema's `enum` block (`Sport`, `NFLPositions`, `NFLRankingTypes`, `NFLScoringTypes`). A spec update that adds a value now fails the suite instead of silently going missing. Required adding `symfony/yaml` as a dev dep. This caught nothing wrong but proves the 31 NflPosition cases and the NflRankingType dedup are faithful.

**AC #3 -- missing key fails early.** `MissingApiKeyException` is thrown from the constructor (blank/whitespace key) and from `fromEnvironment()` (env var absent or blank), so nothing reaches the network. Tested for empty string, spaces, tab, newline, and an unset/whitespace env var.

**Retry policy.** `tries = 3`, 500ms interval, exponential backoff. `handleRetry()` retries `FatalRequestException` (connection-level), 429, and 5xx, but deliberately NOT other 4xx -- a bad key or bad parameter fails identically however often it is asked. Both branches are tested behaviourally through MockClient response sequences.

**Timeouts.** 10s connect, 30s request, expressed by overriding `HasTimeout`'s `getConnectTimeout()`/`getRequestTimeout()` rather than the trait's `property_exists`-based `$connectTimeout` properties -- the properties tripped PHPStan's `property.onlyWritten` and the getters are explicit and directly assertable.

**Deviations from the ticket, both flagged to the user:**
1. The ticket asked for a separate factory resolving the env var; delivered as a `FantasyProsConnector::fromEnvironment()` named constructor instead. A class existing only to hold one static method was ceremony for a single-use path.
2. Enums are `Nfl`-prefixed (`NflPosition`, `NflRankingType`, `NflScoringType`) rather than the flat `Position`/`Scoring`/`RankingType` the ticket names, because the spec defines these per-sport with no shared value set. Sibling enums get added when a second sport does.
3. `NewsCategory` deferred to FANTASY-4, where the news request that consumes it lands, rather than landing an unused enum here.
<!-- SECTION:FINAL_SUMMARY:END -->

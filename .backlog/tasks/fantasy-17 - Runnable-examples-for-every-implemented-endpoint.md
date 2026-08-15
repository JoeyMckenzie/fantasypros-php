---
id: FANTASY-17
title: Runnable examples for every implemented endpoint
status: Done
assignee:
  - '@claude'
created_date: '2026-08-15 22:54'
updated_date: '2026-08-15 22:54'
labels:
  - sdk
  - docs
milestone: SDK v0.1.0
dependencies:
  - FANTASY-16
modified_files:
  - examples/
  - phpstan.neon
priority: medium
ordinal: 17000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
`examples/players.php` was the only worked example, covering one of seven endpoints. The rest of the surface had no runnable demonstration, so the only way to exercise an endpoint by hand was to write the call from scratch against the request's constructor.

An example per endpoint gives the maintainer a manual smoke test and gives FANTASY-8's README something real to draw from, rather than snippets written from the type signatures and never executed.

The value is in them actually running: an example that merely compiles proves nothing about whether the endpoint returns what the DTO expects.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Every implemented endpoint has a self-contained example runnable as php examples/<name>.php
- [x] #2 Each example prints a readable summary rather than dumping the raw DTO
- [x] #3 Each example demonstrates the optional parameters that materially change the response
- [x] #4 Every example is verified by running it against the live API, not only by type-checking
- [x] #5 The examples are covered by the static analysis gate
<!-- AC:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Seven examples

`players`, `rankings`, `consensus-rankings`, `experts`, `injuries`, `news`, `compare-players`. Each is self-contained (autoload, dotenv, connector, call, print) so it can be copy-pasted, and each goes through the connector's endpoint methods from FANTASY-16.

`compare-players.php` derives its player IDs by first calling `consensusRankings()` for quarterbacks and taking the top three, rather than hardcoding IDs that go stale between seasons.

## Three bugs only running them could find

Type-checking passed on all of these; the live run is what caught them.

1. **`comparePlayers()` returned zero expert ranks for every player.** The example asked for `NflScoringType::Ppr`, but the API files QB ranks under **`STD` only** — `PPR` and `HALF` come back empty, because scoring format only changes what a pass-catcher is worth. Probed all three formats across three ranking types to confirm. `PlayerComparison::ranksFor()` returns `[]` rather than erroring, so this fails silently. Switched to `Standard`; now 148 expert ranks per player.

2. **`experts()` returned an empty directory.** Passing any `rankingType` zeroes the result for an unstarted season — all 19 `NflRankingType` cases return 0 for 2026, while 2025 answers `WW` and `WAIVER`. The parameter works; it narrows to experts who published that ranking set, and a season with no rankings has none. Dropped from the example, with the trap documented in place.

3. **`injuries()` truncated `PhysicallyUnableToPerform` to `PhysicallyUn`** at a 12-char column, and `injuryType` is genuinely empty for PUP/IR players.

## PHPStan found six more

`examples` was added to `phpstan.neon` paths, which surfaced five unnecessary `?->` on the left of `??` (`nullsafe.neverNull`) and one short ternary (`ternary.shortNotAllowed`). Fixed at source rather than suppressed — `??` was confirmed to absorb null property access silently, so PHPStan's suggested form is safe.

A seventh followed later: the `array_map` closure in `compare-players.php` needed an explicit `ConsensusRankedPlayer` param type to satisfy `type_coverage.param: 100`.

## Note on quota

Verifying these burned roughly 40 live calls and briefly exhausted the free tier's rate limit (HTTP 429, `{"message":"Limit Exceeded"}`). The account has since moved to 500 requests/day, and truncation is gone from the responses — `players` now returns 8522 of 8522 where the free tier capped it at 10.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Seven runnable examples, one per endpoint: `players`, `rankings`, `consensus-rankings`, `experts`, `injuries`, `news`, `compare-players`. Each is self-contained, prints a readable summary rather than a `var_dump`, and demonstrates the optional parameters that change the response. `compare-players` derives real player IDs from the consensus endpoint instead of hardcoding IDs that go stale.

**Running them was the point — three defects passed type-checking and failed live:**

- `comparePlayers()` returned **zero ranks**: QB ranks live under `STD` only, and `ranksFor()` returns `[]` rather than erroring, so asking for `PPR` failed silently.
- `experts()` returned an **empty directory**: any `rankingType` zeroes the result for a season whose rankings do not exist yet — confirmed across all 19 enum cases.
- `injuries()` **truncated** `PhysicallyUnableToPerform` to `PhysicallyUn`.

Adding `examples` to `phpstan.neon` caught six more issues (five `nullsafe.neverNull`, one short ternary), all fixed at source with no suppression.

Both API quirks are documented in the examples themselves so the next reader does not rediscover them.

Gate green: Pint, PHPStan level max including `examples`, Deptrac, 191 tests. All seven verified against the live API.
<!-- SECTION:FINAL_SUMMARY:END -->

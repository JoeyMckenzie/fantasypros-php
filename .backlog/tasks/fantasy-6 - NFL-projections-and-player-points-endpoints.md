---
id: FANTASY-6
title: NFL projections and player-points endpoints
status: Done
assignee:
  - '@claude'
created_date: '2026-08-14 06:16'
updated_date: '2026-08-15 23:36'
labels:
  - sdk
milestone: SDK v0.1.0
dependencies:
  - FANTASY-2
  - FANTASY-15
  - FANTASY-16
modified_files:
  - src/Data/Api/ProjectedPlayer.php
  - src/Data/Api/PlayerPoints.php
  - src/Data/Envelopes/ProjectionSet.php
  - src/Data/Envelopes/PlayerPointsCollection.php
  - src/Requests/GetProjectionsRequest.php
  - src/Requests/GetPlayerPointsRequest.php
  - src/Concerns/SupportsProjectionEndpoints.php
  - src/FantasyProsConnector.php
  - tests/Requests/GetProjectionsRequestTest.php
  - tests/Requests/GetPlayerPointsRequestTest.php
  - tests/Fixtures/Saloon/nfl/projections.json
  - tests/Fixtures/Saloon/nfl/projections-dst.json
  - tests/Fixtures/Saloon/nfl/projections-ros.json
  - tests/Fixtures/Saloon/nfl/player-points.json
  - examples/projections.php
  - examples/player-points.php
priority: medium
ordinal: 6000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /nfl/{season}/projections (position, week, ros, players list) and GET /nfl/{season}/player-points (start/end week, position, scoring). Projection stats vary by position (QB vs RB/WR/TE vs DST) — model as per-position stat DTOs or a stats map, decide in-ticket.

**The shape of "done" changed after FANTASY-15/16/17.** An endpoint is no longer finished when its Request and DTOs exist. Three things now come with it:

1. **DTOs land in the right sub-namespace.** `src/Data` is split four ways: `Api/` for DTOs hydrated from one decoded JSON object via `fromPayload`, `Envelopes/` for the DTO a request hydrates from a whole `Response` via `fromResponse`, `Infrastructure/` (`Payload` only), `Contracts/`. Deptrac enforces `envelopes -> api -> contracts -> infrastructure` one-way, and `requests` may reach **only** `data.envelopes`. A DTO in the wrong folder fails `composer test:arch`.

2. **The endpoint gets a connector method.** Consumers call `$connector->projections(...)`, never `send(new ...)->dto()`. Methods live in `Supports*Endpoints` traits under `src/Concerns` (`FantasyPros\Concerns`) and are composed onto `FantasyProsConnector`. A new trait is picked up by Deptrac automatically because the `connector` layer collects the whole `Concerns` directory. Return the concrete envelope via the request's `createDtoFromResponse($this->send($request))` — **not** `$this->send($request)->dto()`, which is `mixed` and fails PHPStan level max.

3. **The endpoint gets a runnable example** in `examples/`, verified by actually running it against the live API rather than only type-checking. `examples/` is in the PHPStan paths.

Read `examples/` and `src/Concerns/SupportsRankingEndpoints.php` before starting — they are the pattern to copy.

**Two API quirks found while building the examples, both relevant here.** Scoring format only changes what a pass-catcher is worth, so QB/K/DST ranks come back under `STD` with `PPR` and `HALF` empty — expect the same asymmetry in projections and check it rather than assuming. And a season that has not started returns empty sets for parameters that narrow by published ranking data, without erroring. Record a fixture and read it before modelling any DTO.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Weekly and ROS projections requests covered by fixture tests
- [x] #2 Player points weekly breakdown hydrates correctly
- [x] #3 DTOs are placed in the correct src/Data sub-namespace and composer test:arch passes
- [x] #4 Both endpoints are reachable as connector methods via a Supports*Endpoints trait in src/Concerns, returning their concrete envelope type
- [x] #5 Each endpoint has a runnable example in examples/ verified against the live API
- [x] #6 The full gate passes: composer test, fmt:check, refactor:check, lint, test:arch
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Probe both endpoints live and read real payloads → verify: shapes captured before any DTO is written
2. Decide the projection stat model from the payloads, not the spec → verify: decision documented with evidence
3. Api DTOs (ProjectedPlayer, PlayerPoints) + Envelopes (ProjectionSet, PlayerPointsCollection) → verify: composer test:arch green
4. Requests + SupportsProjectionEndpoints trait on the connector → verify: PHPStan level max, no assert/@var/cast
5. Fixture tests, recorded from the live API → verify: composer test offline
6. Runnable examples for both endpoints → verify: executed live, not just type-checked
7. Full gate → verify: ci-lint + ci-test green
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## The decision: a stats map, and the spec is why

The ticket left per-position stat DTOs vs a stats map open. Reading real payloads for QB, RB and DST settled it against the typed variants, for three independent reasons:

1. **The spec's per-position schemas are factually wrong.** `NFLDSTPlayerProjections` declares `def_pa_a` through `def_pa_g` — seven point-allowed buckets. The live route returns `def_pa` and `def_tyda` and none of the seven. Generating four DTOs from those schemas produces classes that throw on genuine responses, which is precisely the failure CONTRIBUTING warns about.
2. **`2pt_tds` is not a legal property name.** It leads with a digit, so a typed variant could only ever expose it as `$player->{'2pt_tds'}`.
3. **Four variants put `instanceof` back at every call site**, undoing what FANTASY-16 set out to remove.

`RankedPlayer::$ranks` already models a heterogeneous stat bag as a map, so this follows house precedent rather than inventing a shape. Access is `points(NflScoringType)` for the three universal keys and `stat(string)` for the rest, which answers null for a key the position does not carry instead of erroring.

## What the spec got wrong, found only by recording

- **`stats` is an object, not an array.** The spec types it `type: array, items: oneOf[...]`.
- **`players` is `null`, not `[]`, on a rest-of-season request.** `Payload::objects()` rejects a non-array, so this would have thrown. `has()` is `isset()`-based, so null and absent both fall through to the empty list — no change to `Payload` needed.
- **`games`, `points`, `average` and `weeks` are all absent for players who did not appear in the requested range**, despite the spec marking every one required. **26 of 86** quarterbacks in the recorded set are in that state — an identity block and nothing else. They now read as a scoreless line, since not playing is a real answer and zero is the truthful count. Recording is what caught this: the first record run failed with `Expected "games" to be an integer, got null`.
- **`filters` is documented on projections as "a comma delimited string of expert IDs"**, copied from the ranking routes. Projections have no experts, so it is deliberately not exposed.

## The ticket's predicted quirk did not reproduce

The description warned that QB/K/DST would come back under `STD` with `PPR` and `HALF` empty, as they do on the ranking routes. **Checked rather than assumed, and it is not true here** — every position carries all three points keys. For a QB or a DST they simply hold the same number (Denver: 9.04 across all three), because scoring format only changes what a pass-catcher is worth. Pinned by a test so the difference between the two route families stays visible.

Rest-of-season *is* empty, but for its own reason: 2025 is over and 2026 has not started, so both return `count: 0`. The parameter works; there is nothing left to project.

## Fixtures stayed small by narrowing

`players=16393:22968` narrows projections to 993 bytes. Three of the four new fixtures are 1.4–2.4 KB, in line with the existing set. `player-points` has no player filter and is 18 KB — the largest fixture in the repo, justified because it carries the 26 identity-only players that document the quirk above.

The `min=true` case is pinned with a hand-built `Payload` rather than a second recorded fixture, since its only difference is what it omits.

## Infection: two escapees, killed by deletion rather than an ignore

`minMsi` is now **100** (raised in `ci: init`), so both new escapees had to die. Both came from a single method, `weeksPlayed()` — an `array_map(intval(...), array_keys(...))` plus `sort()`.

The `intval` unwrap is the same provably-equivalent-cast category as the three exclusions already in `infection.json5`: `Payload::floatMap` keys come from a decoded JSON object, and PHP canonicalises decimal-integer string keys to int on insertion, so the cast is identity for every week number.

Rather than add a fourth documented ignore, the method was **removed**. It was a thin wrapper over `array_keys()` on an already-public readonly array, and it was the sole source of both mutants. Callers iterate `$player->weeks` directly, which is shorter at the call site. MSI back to **100%, 0 escaped, no new ignores** — CONTRIBUTING's "a real test, not an ignore" satisfied by deleting the thing that needed one.

## Verified, not assumed

Deptrac reports 0 violations across 279 allowed dependencies, but a clean run proves nothing on its own — FANTASY-16's trap. `debug:layer` confirms all eight new class-likes land in their intended layers: the trait in `connector`, both requests in `requests`, both envelopes in `data.envelopes`, both DTOs in `data.api`.

Both examples were executed against the live API, not merely type-checked. The live run is what confirmed `min=true` genuinely drops the name and team, and that the identity-only quirk reproduces across a different week range (63 of 86 played over weeks 1–6).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Both endpoints are implemented, typed, fixture-tested and demonstrated live. **219 tests / 1013 assertions** (up from 191/793), PHPStan level max clean over 85 files, Deptrac 0 violations, Infection **MSI 100%**.

Consumers call `$connector->projections(...)` and `$connector->playerPoints(...)` through a new `SupportsProjectionEndpoints` trait, each returning its concrete envelope with no `assert()`, cast or `instanceof`.

**The modelling decision went to a stats map over per-position DTOs, and the spec is the reason.** `NFLDSTPlayerProjections` declares seven `def_pa_a`…`def_pa_g` buckets that the live route has never returned — it sends `def_pa` and `def_tyda` instead. Four DTOs generated from those schemas would throw on genuine responses. Two further nails: `2pt_tds` leads with a digit so it could never be a property name, and typed variants would put `instanceof` back at every call site.

**Recording the fixtures earned its keep — three spec lies surfaced only by running against the API:**

- `stats` is an object; the spec types it as an array.
- A rest-of-season request answers `players: null`, not `[]` — which would have thrown in `Payload::objects()`.
- `games`, `points`, `average` and `weeks` are **all absent** for players who did not appear in the range, though the spec marks each required. 26 of 86 quarterbacks are in that state. The first record run failed outright on it.

**The quirk the ticket predicted did not reproduce, and that is worth knowing.** Projections do *not* share the ranking routes' STD-only asymmetry — every position carries all three points keys, holding one number for a QB or DST rather than coming back empty. Checked across three positions and pinned by test.

**Infection's two escapees were removed rather than excluded.** Both came from `weeksPlayed()`, a thin `array_keys` + `sort` wrapper whose `intval` map was the same provably-equivalent cast already excluded three times in the config. Deleting the method killed both and shrank the public surface; callers iterate `$player->weeks` directly.

Layer coverage was confirmed with `debug:layer` rather than trusted from a green Deptrac run, per FANTASY-16's trap: all eight new class-likes land in their intended layers.

This completes the endpoint surface — 9 of 9 routes the spec exposes for `/{sport}` and `/nfl` are implemented.
<!-- SECTION:FINAL_SUMMARY:END -->

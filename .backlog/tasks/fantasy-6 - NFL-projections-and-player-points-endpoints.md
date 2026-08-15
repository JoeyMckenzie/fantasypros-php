---
id: FANTASY-6
title: NFL projections and player-points endpoints
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
updated_date: '2026-08-15 22:54'
labels:
  - sdk
milestone: SDK v0.1.0
dependencies:
  - FANTASY-2
  - FANTASY-15
  - FANTASY-16
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
- [ ] #1 Weekly and ROS projections requests covered by fixture tests
- [ ] #2 Player points weekly breakdown hydrates correctly
- [ ] #3 DTOs are placed in the correct src/Data sub-namespace and composer test:arch passes
- [ ] #4 Both endpoints are reachable as connector methods via a Supports*Endpoints trait in src/Concerns, returning their concrete envelope type
- [ ] #5 Each endpoint has a runnable example in examples/ verified against the live API
- [ ] #6 The full gate passes: composer test, fmt:check, refactor:check, lint, test:arch
<!-- AC:END -->

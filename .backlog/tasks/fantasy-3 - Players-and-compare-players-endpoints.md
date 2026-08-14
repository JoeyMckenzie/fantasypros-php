---
id: FANTASY-3
title: Players and compare-players endpoints
status: Done
assignee: []
created_date: '2026-08-14 06:16'
updated_date: '2026-08-14 18:27'
labels:
  - sdk
dependencies:
  - FANTASY-2
modified_files:
  - src/Sdk/Requests/GetPlayersRequest.php
  - src/Sdk/Requests/ComparePlayersRequest.php
  - src/Sdk/Data/Payload.php
  - src/Sdk/Data/PlayerCollection.php
  - src/Sdk/Data/NflPlayer.php
  - src/Sdk/Data/PlayerComparison.php
  - src/Sdk/Data/ComparedPlayer.php
  - src/Sdk/Data/ComparedExpert.php
  - src/Sdk/Data/ExpertRank.php
  - src/Sdk/Data/ApiLimits.php
  - src/Sdk/Enums/EcrFilter.php
  - src/Sdk/Enums/ExternalIdSource.php
  - src/Sdk/Enums/ComparisonRankingType.php
  - src/Sdk/Enums/ComparisonDetails.php
  - src/Sdk/Exceptions/InvalidComparisonException.php
  - src/Sdk/Exceptions/UnexpectedPayloadException.php
  - tests/Sdk/RequestTestCase.php
  - tests/Sdk/Requests/GetPlayersRequestTest.php
  - tests/Sdk/Requests/ComparePlayersRequestTest.php
  - tests/Sdk/Data/PayloadTest.php
  - tests/Fixtures/Saloon/nfl/players.json
  - tests/Fixtures/Saloon/nfl/compare-players.json
priority: medium
ordinal: 3000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /{sport}/players (player id, updated-since, ecr, external_ids, pos_rank params) and GET /{sport}/compare-players (players, position, ranking_type, details). Readonly DTOs for NFL player + comparison results under Sdk/Data via createDtoFromResponse.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Requests build correct paths and query strings (MockClient tests)
- [x] #2 NFL fixture responses hydrate into DTOs
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
## Steps

1. `src/Sdk/Requests/GetPlayersRequest.php` -- `GET /{sport}/players`
   - constructor: `Sport $sport`, and nullable `?int $playerId`, `?DateTimeImmutable $updatedSince`, `?EcrFilter $ecr`, `?string $externalIds`, `?bool $withPositionRank`
   - `defaultQuery()` omits nulls so we never send `?player=` empty
   - `update` is formatted `Y-m-d`; `show=pos_rank`; `ecr=included|excluded` via a small `EcrFilter` enum
2. `src/Sdk/Requests/ComparePlayersRequest.php` -- `GET /{sport}/compare-players`
   - required: `Sport $sport`, `int[] $playerIds` (colon-joined), `NflPosition $position`
   - optional: `?ComparisonRankingType $rankingType` (draft|weekly|ros), `?ComparisonDetails $details` (players|experts|all), `int[] $expertIds`, `?int $year`, `?int $week`
   - spec says "Compare 2-4 players"; guard the count and throw rather than let the API 400
3. DTOs under `src/Sdk/Data/`, all `final readonly`, hydrated via `createDtoFromResponse()`:
   - `PlayerCollection` (sport, count, season, week, `NflPlayer[]`) -- note the spec types `season`/`week`/`count` inconsistently (`count` int, `season`/`week` strings); DTO normalises to int
   - `NflPlayer` -- ids, names, `positions`, team, `rank_ecr`/`rank_adp`/`rank_ecr_pos`/`rank_ecr_ppr`/`rank_ecr_half`/`rank_adp_ppr`, birthdate, age, `rookie` ("Y" -> bool). Only `player_id`/`player_name`/`position_id`/`team_id` are truly always present; everything else is nullable, because the `ecr=excluded` variant omits rank fields.
   - `PlayerComparison` (sport, year, week, positionId, rankingType, rankings, players, experts)
     - the NFL `rankings` shape is scoring-type -> player-id -> `[{expert_id, rank}]`, so it maps to `array<NflScoringType, array<int, ExpertRank[]>>`; MLB/NBA/NHL drop the scoring level, which is why this DTO is NFL-shaped for now
     - expert id `_0` appears in the spec example (it is the consensus row, not a real expert id) -- keep `ExpertRank::$expertId` a string so it survives
   - `ComparedPlayer`, `ComparedExpert`, `ExpertRank`
4. Tests in `tests/Sdk/`
   - URL + query construction per request, including the null-omission and colon-joining behaviour
   - DTO hydration off recorded fixtures (`nfl/players`, `nfl/compare-players`)
   - guard-clause tests for the player-count bound
   - verify: `composer test` green offline; `composer test:mutate` MSI reviewed and a floor agreed (carried over from FANTASY-1)
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Enums added here: `EcrFilter`, `ExternalIdSource` (21 sources), `ComparisonRankingType` (draft/weekly/ros), `ComparisonDetails`. These are inline parameter enums in the spec rather than named `components.schemas` entries, so unlike Sport/NflPosition/NflRankingType/NflScoringType they are NOT covered by EnumsMatchSpecTest's spec-parity check -- their values are asserted only through the query-string tests. Worth extending that test to inline parameter enums if a future spec update lands.

Note `ComparisonRankingType` is deliberately separate from `NflRankingType`: the compare-players endpoint takes its own lowercase three-value set (draft/weekly/ros), not the 19-value ranking-type vocabulary.

Also fixed a test bug that only surfaced in record mode: the env-precedence test set the process env without first clearing $_SERVER/$_ENV, so once .env had populated those with the real key, $_SERVER won and the assertion diff printed the live key into the output. It now clears all three sources first, which both fixes the test and removes a way for the secret to reach a failure message.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Both endpoints done. 94 tests green offline, PHPStan level max clean, Rector/Pint clean, Deptrac 0 violations, Infection MSI 100%.

**Method: recorded fixtures before DTOs.** Rather than model the DTOs on the spec's examples and hope, I captured both endpoints from the live API first and modelled against what actually came back. That was the right call -- the real payloads differ from the spec in five ways the spec never mentions:
1. Both responses carry `public_api_limited` and `tier`; players also carries `limit`.
2. `limit` is **10** on the free tier, and the response still reports the full `count`. A caller that ignores this silently believes it has a complete roster. Mapped to an `ApiLimits` DTO with a `PlayerCollection::truncated()` helper rather than discarded.
3. Players carry `draft_class`, which the spec does not list.
4. Players do **not** carry the `rookie` field the spec marks on NFLPlayer.
5. Every expert in the comparison carries a `ranks` array of scoring formats -- not just the consensus row, which is what I first assumed and wrote a (failing) test for. What actually distinguishes the `_0` consensus pseudo-expert is a blank `expert_source_id`.

**Requests.** `GetPlayersRequest` (player, update, ecr, external_ids, show) and `ComparePlayersRequest` (players, position, ranking_type, details, experts, year, week). Unset options are filtered out rather than sent empty, asserted both on the wire query string and on the query repository -- `http_build_query` drops nulls on its own, so the repository assertion is what actually pins the intent. Week 0 (preseason) is explicitly tested to survive the filter.

**DTOs.** `PlayerCollection`, `NflPlayer`, `PlayerComparison`, `ComparedPlayer`, `ComparedExpert`, `ExpertRank`, `ApiLimits`, hydrated via `createDtoFromResponse()`. All ranks are nullable, because `ecr=excluded` returns players with no consensus ranking and `rank_ecr_pos` only appears with `show=pos_rank`.

Two design notes:
- A `Payload` reader centralises JSON coercion. The API is inconsistent about scalar types -- `count` arrives as an int while `season`, `week` and every `rank` arrive as numeric strings -- so coercion lives in one tested place instead of scattered across every DTO. It has its own direct test suite; the remaining endpoint tickets get it for free.
- `PlayerComparison` is NFL-shaped on purpose: for NFL the rankings nest scoring format over player ID, whereas MLB/NBA/NHL drop the scoring level entirely. A second sport should get its own DTO rather than a nullable middle layer here.

**A real bug mutation testing found:** PHP silently casts numeric-string array keys to ints, so the `array<string, ...>` declarations on the ranking/player/expert maps were false. Corrected to int-keyed (players) and `array-key` (experts, which include the `_0` sentinel), with explicit casts where the declared type needs to stay true.

**Deviation from the plan:** the plan proposed guarding the "2-4 players" bound the spec's description states. Not enforced -- the spec contradicts itself, its own `examples` block showing a valid single-player request. Only the genuinely-invalid empty list is guarded (`InvalidComparisonException`), rather than inventing a restriction the API may not have. `playerIds` is typed `list<int>` rather than `non-empty-list<int>` so that runtime guard is reachable and meaningful, since these values arrive as MCP tool arguments rather than from static call sites.

**Fixture size:** the first compare-players recording was 816KB, because `details=all` returns all 2,559 experts regardless of who is being compared. Narrowing `experts` to two IDs brought it to 3.6KB while still exercising expert mapping and the consensus row.
<!-- SECTION:FINAL_SUMMARY:END -->

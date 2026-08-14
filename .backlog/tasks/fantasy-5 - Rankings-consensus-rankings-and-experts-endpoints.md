---
id: FANTASY-5
title: 'Rankings, consensus rankings, and experts endpoints'
status: Done
assignee:
  - '@claude'
created_date: '2026-08-14 06:16'
updated_date: '2026-08-14 20:32'
labels:
  - sdk
dependencies:
  - FANTASY-2
modified_files:
  - src/Sdk/Requests/GetRankingsRequest.php
  - src/Sdk/Requests/GetConsensusRankingsRequest.php
  - src/Sdk/Requests/GetExpertsRequest.php
  - src/Sdk/Data/RankingsCollection.php
  - src/Sdk/Data/RankedPlayer.php
  - src/Sdk/Data/ConsensusRankings.php
  - src/Sdk/Data/ConsensusRankedPlayer.php
  - src/Sdk/Data/RankingExpert.php
  - src/Sdk/Data/ExpertDirectory.php
  - src/Sdk/Data/ExpertProfile.php
  - src/Sdk/Data/Payload.php
  - src/Sdk/Enums/ExpertsDetail.php
  - src/Sdk/Enums/RankMetric.php
  - tests/Sdk/Requests/GetRankingsRequestTest.php
  - tests/Sdk/Requests/GetConsensusRankingsRequestTest.php
  - tests/Sdk/Requests/GetExpertsRequestTest.php
  - tests/Sdk/Data/PayloadTest.php
  - tests/Fixtures/Saloon/nfl/rankings.json
  - tests/Fixtures/Saloon/nfl/consensus-rankings.json
  - tests/Fixtures/Saloon/nfl/experts.json
  - infection.json5
priority: medium
ordinal: 5000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /{sport}/{season}/rankings, GET /{sport}/{season}/consensus-rankings (position, scoring, week, experts=show), GET /{sport}/{season}/rankings/experts. DTOs for ranked players (ECR, min/max ranks) and expert profiles.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Season/week path+query combinations covered by tests
- [x] #2 Consensus rankings hydrate DTOs incl. rank ranges when range=true
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
## Decisions confirmed with the user (2026-08-14)

| Question | Answer |
|---|---|
| `type` param on `/rankings` (only legal value `DRAFTERS`) vs on the other two (19-value `NFLRankingTypes`) | `/rankings` gets `bool $includeDrafters` mapping to `type=DRAFTERS` or omission -- same presence-signalled shape as FANTASY-4's `includeMinors`/`includeProbabilities`. The other two take `?NflRankingType` normally. |
| The parallel expert maps on the consensus-rankings envelope (`expert_pub`, `expert_name`, `expert_twitter`) | Fold into one `array<int, RankingExpert>` so a caller does one lookup per expert instead of zipping three maps together. |
| Scope | Deliver all three endpoints as one ticket rather than carving out experts. |

**No new position/scoring/ranking-type enums needed.** The spec's `SPORTPositions`, `SPORTRankingTypes` and `SPORTScoringTypes` are `oneOf` wrappers over the per-sport schemas, so these requests reuse the existing `NflPosition`, `NflScoringType` and `NflRankingType`.

**Method (third time): record real fixtures BEFORE modelling DTOs.** FANTASY-3 found five undocumented divergences, FANTASY-4 found two that would have made a spec-modelled DTO throw outright.

## Steps

1. **Enum** -- `ExpertsDetail` (`show`, `available`) for the consensus-rankings `experts` param. Inline parameter enum, so like `EcrFilter`/`NewsCategory` it is not covered by EnumsMatchSpecTest; pinned by the query-string tests. Cases blank-line separated per the review convention.

2. **Requests**, all taking a required `int $season` folded into `resolveEndpoint()` -- the first path shape beyond `/{sport}/...`:
   - `GetRankingsRequest` -> `/{sport}/{season}/rankings`: `week`, `player`, `filters`, `min`, `range`, `rankstats`, `includeDrafters`.
   - `GetConsensusRankingsRequest` -> `/{sport}/{season}/consensus-rankings`: `position` (REQUIRED per the spec's `Position` parameter), `type`, `scoring`, `week`, `include_idp`, `filters`, `experts`.
   - `GetExpertsRequest` -> `/{sport}/{season}/rankings/experts`: `position` (optional), `type`, `scoring`, `include_overall`.
   - `min`/`range`/`rankstats` are `'true'|'false'` two-valued with default `'false'`, so they map to booleans that send `'true'` or omit. `include_idp`/`include_overall` are the single-value `'true'` kind, as in FANTASY-4.

3. **Request tests** covering the season/week path+query matrix (AC #1), the null-omission cases via `queryParametersFor()`, and week 0.

4. **Two spec contradictions to settle empirically rather than by guessing**, both logged to notes with whatever the API actually does:
   - **`filters` delimiter**: the parameter description says "a *comma* delimited string of expert IDs" while its own `pattern` (`^(\d+)((?:\:\d+)+)?$`) and example (`'345:332:12'`) say colon. Record the same request both ways and keep what the API honours.
   - **AC #2 may name the wrong endpoint**: it says consensus rankings hydrate rank ranges "when range=true", but `range` is documented only on `/rankings`, not on `/consensus-rankings`. Test whether consensus-rankings accepts it undocumented; if it does not, propose amending the AC to name `/rankings`, per the FANTASY-1 precedent for stale ACs.

5. **Record fixtures** via a throwaway capture test in record mode. Narrow hard -- `/rankings` unfiltered is every ranked NFL player, and FANTASY-3's first recording was 816KB. Single position plus a couple of expert IDs; `ls -la` and re-record narrower before anything is committed.

6. **DTOs modelled on the recordings**, not on the spec:
   - Envelopes (`fromResponse`): `RankingsCollection`, `ConsensusRankings`, `ExpertDirectory`.
   - Items (`fromPayload`, implementing `ApiDataContract`): `RankedPlayer`, `ConsensusRankedPlayer`, `RankingExpert`, `ExpertProfile`, `ExpertsAvailable`.
   - Naming deliberately keeps clear of the existing `ComparedExpert` from compare-players -- three distinct expert shapes now live under `Data/`, called out in the docblocks.
   - Reuse `ApiLimits` if these envelopes carry the tier fields, as every endpoint so far has.

7. **Hydration tests** (AC #2) via `dtoFrom()`, asserting envelope fields, one item's every mapped field, and the nullable/edge rows the fixtures actually contain.

## Verification

`composer test`; `composer fmt && composer refactor && composer lint` (that order); `composer fmt:check && composer refactor:check`; `./vendor/bin/deptrac analyse --config-file=deptrac.php` 0 violations; `composer test:mutate` MSI >= 95; plus a full record-mode run, which also runs FixtureSafetyTest's live-key scan over the new fixtures. Infection escapees get a real test, not an ignore, unless provably behaviour-neutral.

## Out of scope

MLB/NBA/NHL ranking and expert DTOs (per-sport response shapes, separate tickets), the MLB-only `site_eligibility` parameter, and committing -- the working tree stays dirty for review.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Both spec contradictions settled against the live API

**1. `filters` must be COLON-delimited, and comma fails silently.** Probed all three forms against `/nfl/2025/consensus-rankings`:

| Sent | `total_experts` | Effect |
|---|---|---|
| `filters=1091:2791` | 2 | filter applied |
| `filters=1091,2791` | 40 | **silently ignored** -- identical to sending nothing |
| (omitted) | 40 | baseline |

No error, no warning: a caller following the parameter's own description ("a comma delimited string of expert IDs") gets the full unfiltered expert set and no indication anything went wrong. The description is actually describing the *response*, which echoes the applied filter back comma-joined (`filters: '1091,2791'`). The schema's `pattern` and example were right. The SDK sends colons.

**2. AC #2 stands as written -- no amendment needed, but the mechanism is not what the wording implies.** `range` is documented only on `/rankings`, and I first read both `range` and `rankstats` as no-ops there because neither changes the player object's top-level keys. That reading was wrong: on `/rankings` they add metric blocks *inside* the nested `rank` object.

| `/rankings` query | `rank` metric keys |
|---|---|
| (none) | `ECR` |
| `range=true` | `ECR`, `ECR_MIN`, `ECR_MAX` |
| `rankstats=true` | `ECR`, `ECR_AVG`, `ECR_STD` |
| both | all five |

Meanwhile `/consensus-rankings` returns flat `rank_min`/`rank_max`/`rank_ave`/`rank_std` on every player **unconditionally** -- passing `range=true` there changes nothing (verified: identical key sets). So consensus rankings do hydrate rank ranges, satisfying AC #2, but they do so without needing `range` at all. `GetConsensusRankingsRequest` therefore does not expose a `range` parameter, since the endpoint neither documents nor honours one.

## Other spec-vs-reality divergences

3. **Spec says `expert_name`; the live envelope sends `expert_names`.** Modelling from the spec would have produced a permanently empty expert-name map -- exactly the failure mode the record-first rule exists to prevent.
4. **Spec says `defaults` on the expert object; the live payload sends `default`.** The same mistake in the opposite direction.
5. **The `/rankings` `rank` field is three levels deep** -- metric -> scoring -> position -> value -- which the spec does not describe at all. The scoring vocabulary there is wider than `NFLScoringTypes`: alongside `STD`/`PPR`/`HALF` it carries `ROS-STD`, `ROS-PPR`, `ROS-HALF` and `DYN`, none of them enumerated anywhere in the spec. Left as string keys for the same reason `team_id` was: there is no spec enum to pin them against.
6. **`ECR_AVG` mixes ints and floats inside a single block** (`{'RB': 8, 'FLX': 8.7}`), so the reader has to accept both.
7. **Consensus `rank_min`/`rank_max`/`rank_ave`/`rank_std` arrive as numeric strings while `rank_ecr` is an int** -- the same looseness `Payload` already exists to absorb.
8. Consensus `type` is a display label (`'Weekly PPR'`) while `ranking_type_name` is lowercase (`'weekly'`); the spec types the latter as the uppercase `NFLRankingTypes` vocabulary.
9. **All three envelopes carry `limit`/`public_api_limited`/`tier` and all three truncate on the free tier** (rankings 1 of 1, consensus 10 of 61, experts 10 of 189), so all three reuse `ApiLimits` and get a `truncated()` helper.
10. The experts payload carries per-expert `accuracy_draft`, `accuracy_weekly` and `accuracy_weekly_last_season` maps, plus an envelope-level `accuracy_weekly_last_season` -- none in the spec's `Expert`/`Experts` schemas. The last two are per-expert optional (absent on some rows).
11. **`include_overall` appears to be a no-op**: with and without it the expert key set is identical and the `ALL` accuracy key is present either way. Kept on the request because it is documented and harmless, but it buys nothing observable.

## Deliberately not modelled

The `/rankings` envelope's `experts` (scoring -> position -> count) and `ecr_experts` (scoring -> position -> list of ~40 expert IDs) maps. Same call as FANTASY-4's `covids`: no stated use needs them, and mapping them would add three levels of nested generics to satisfy PHPStan for data nothing reads. Noted here so a future ticket can pick them up deliberately rather than rediscover them.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
All three endpoints done. 191 tests green offline (up from 156), PHPStan level max clean, Rector/Pint clean, Deptrac 0 violations, Infection MSI 100%. Verified green in record mode too, which runs FixtureSafetyTest's live-key scan over the three new fixtures.

**AC #1 -- season/week path+query combinations.** These are the first routes with a path segment beyond the sport, so `int $season` is a required constructor argument folded into `resolveEndpoint()`. A data-provider test pins the path across sports and seasons, including the nested `/{sport}/{season}/rankings/experts` shape; week 0 and the null-omission cases are asserted on the query repository rather than the wire string, since `http_build_query` drops nulls by itself.

**AC #2 -- consensus rankings hydrate rank ranges.** They do, and `ConsensusRankedPlayer` carries `rankMinimum`/`rankMaximum`/`rankAverage`/`rankStandardDeviation` plus a `rankSpread()` helper. The AC's "when range=true" clause turned out not to describe reality -- see below.

**Both spec contradictions settled against the live API rather than guessed** (detail in the notes):

1. **`filters` must be colon-delimited, and comma fails silently.** The parameter's own description says "comma delimited"; its `pattern` and example say colon. Sending colons applies the filter (`total_experts: 2`); sending commas returns the full unfiltered set (`total_experts: 40`) with no error. The description is actually describing the response, which echoes the applied filter back comma-joined. A caller who followed the docs would get wrong data silently. The SDK sends colons and there is a test pinning both halves of that asymmetry.

2. **`range=true` is not what makes consensus ranges appear.** `/consensus-rankings` returns the flat range fields unconditionally -- verified identical key sets with and without `range` -- so `GetConsensusRankingsRequest` deliberately does not expose the parameter. On `/rankings`, where it is documented, `range` and `rankstats` do work, but by adding metric blocks *inside* the nested `rank` object rather than as top-level player fields. I initially misread both as no-ops because I compared the wrong nesting level; the corrected probe is tabulated in the notes. AC #2 needed no amendment.

**Two more divergences that would have produced silently-broken DTOs**, both mirror images of each other: the spec documents `expert_name` where the payload sends `expert_names`, and documents `defaults` where the payload sends `default`. Either would have hydrated as a permanently empty map with no error.

**The `/rankings` rank structure is three levels deep** -- metric -> scoring -> position -> value -- which the spec does not describe at all. Modelled as a nested map behind a `rank(RankMetric, scoring, position)` accessor, following the `PlayerComparison::ranksFor()` precedent. The scoring level carries `ROS-STD`, `ROS-PPR`, `ROS-HALF` and `DYN` alongside the documented three, none of them enumerated anywhere, so those keys stay strings for the same reason `team_id` did. `RankMetric` is a new enum for the five metric blocks.

**Four `Payload` additions** -- `intMap`, `floatMap`, `stringMap`, `boolMap` -- sharing one private `scalarMap` body. Every one was forced by a real payload shape: expert-ID to rank, the nested rank values (where `ECR_AVG` mixes ints and decimals inside a single block), position to timestamp, and position to default flag. They reject an unreadable entry rather than silently dropping it.

**Design choices confirmed with the user before coding:** `/rankings` exposes its single-valued `type=DRAFTERS` as `bool $includeDrafters` rather than an enum, matching FANTASY-4's presence-signalled flags -- worth noting that `NflRankingType` does carry a `Drafters` case, but accepting the full 19-value enum there would let callers send values the route rejects, and the docblock says so. The consensus envelope's three parallel expert maps fold into one `array<int, RankingExpert>`.

Three distinct expert shapes now live under `Data/` -- `RankingExpert` (folded from the consensus envelope), `ExpertProfile` (the directory entry) and the pre-existing `ComparedExpert` -- each cross-referenced in its docblock. `RankingExpert` deliberately does not implement `ApiDataContract`: it is assembled from sibling maps, not hydrated from one JSON object, so `fromPayload` does not fit it.

**Mutation testing found three real gaps**, all now tested: `rankSpread()`'s two-sided null guard (the recorded set has both bounds on every player, so it is pinned by direct hydration), and the un-truncated branch of `truncated()` on both new envelopes (both fixtures are truncated, so the equal-count case is constructed). One ignore added and documented in `infection.json5`: `CastString` on `RankedPlayer::readRanks`, the same array-key-honesty cast already ignored on `PlayerComparison::readRankings`.

**Deliberately not modelled:** the `/rankings` envelope's `experts` and `ecr_experts` maps, noted in the task so a later ticket can pick them up on purpose.

**Not committed** -- working tree left dirty for review, as since FANTASY-1.
<!-- SECTION:FINAL_SUMMARY:END -->

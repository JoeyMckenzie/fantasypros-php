---
id: FANTASY-4
title: News and injuries endpoints
status: Done
assignee:
  - '@claude'
created_date: '2026-08-14 06:16'
updated_date: '2026-08-14 19:57'
labels:
  - sdk
dependencies:
  - FANTASY-2
modified_files:
  - src/Sdk/Requests/GetNewsRequest.php
  - src/Sdk/Requests/GetInjuriesRequest.php
  - src/Sdk/Data/NewsFeed.php
  - src/Sdk/Data/NewsItem.php
  - src/Sdk/Data/InjuryReport.php
  - src/Sdk/Data/NflInjury.php
  - src/Sdk/Data/Payload.php
  - src/Sdk/Enums/NewsCategory.php
  - src/Sdk/Enums/NewsOrder.php
  - src/Sdk/Enums/NflInjuryStatus.php
  - tests/Sdk/Requests/GetNewsRequestTest.php
  - tests/Sdk/Requests/GetInjuriesRequestTest.php
  - tests/Sdk/Data/PayloadTest.php
  - tests/Fixtures/Saloon/nfl/news.json
  - tests/Fixtures/Saloon/nfl/injuries.json
  - tests/Fixtures/Saloon/nfl/injuries-in-season.json
  - infection.json5
priority: medium
ordinal: 4000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /{sport}/news (fpid, limit, category, order_by) and GET /{sport}/injuries (year, week, include_probabilities, team_id/player_ids colon lists). DTOs for news items and NFL injury entries.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Colon-delimited list params serialize correctly from PHP arrays
- [x] #2 NFL fixtures hydrate into DTOs with tests passing
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
## Decisions confirmed with the user (2026-08-14)

| Question | Answer |
|---|---|
| Leaked API key (previous session) | Rotated -- live recording cleared to proceed |
| `.env` var-name mismatch | User renames `FANTASY_PROS_API_KEY` -> `FANTASYPROS_API_KEY` in `.env`; SDK name unchanged. Do not edit the secrets file. |
| `include_minors` / `include_probabilities` | Boolean constructor args mapping to `'true'`/omission, matching the existing `withPositionRank` -> `show=pos_rank` precedent in GetPlayersRequest |
| `team_id` typing | `list<string>` -- the spec's `TeamId` schema is a bare string with no `enum` block, so an invented `NflTeam` enum could not be pinned by EnumsMatchSpecTest and would be free to drift |

**Method (carried over from FANTASY-3): record real fixtures BEFORE modelling DTOs.** The live
payloads diverged from the spec in five undocumented ways on the players endpoints; modelling on
the spec's examples would have been wrong.

## Steps

1. **Enums** -- `src/Sdk/Enums/`, backed string enums.
   - `NewsCategory` (injury, recap, transaction, rumor, breaking) -- deferred here deliberately by
     FANTASY-2 rather than landing unused. The spec's `null` enum member is the "omitted" case, so
     it is a nullable parameter, not a case.
   - `NewsOrder` (updated, created; spec default `created`).
   - Both are inline *parameter* enums, not `components.schemas` entries, so -- like `EcrFilter`,
     `ComparisonRankingType`, `ComparisonDetails` -- they are NOT covered by EnumsMatchSpecTest.
     Their values are pinned by the query-string tests instead. No change to EnumsMatchSpecTest.

2. **Request classes**, copying the shape of `ComparePlayersRequest`/`GetPlayersRequest`
   (`#[Override] protected Method $method`, `sprintf('/%s/...', $sport->pathSegment())`,
   `defaultQuery()` + `array_filter(... !== null)`).
   - `GetNewsRequest(Sport, ?int $playerId, ?int $limit, ?NewsCategory, ?NewsOrder $orderBy)`.
     `playerId` -> `fpid`. Spec defaults (limit=25, order_by=created) left to the API rather than
     duplicated client-side.
   - `GetInjuriesRequest(Sport, ?int $year, ?int $week, list<string> $teamIds, list<int> $playerIds,
     bool $includeMinors, bool $includeProbabilities)`. Both lists colon-joined (AC #1); booleans map
     to the literal `'true'` or null; week 0 (preseason) must survive the null filter.

3. **Request tests** -- `tests/Sdk/Requests/{GetNewsRequestTest,GetInjuriesRequestTest}.php`
   extending `RequestTestCase`. `#[Test]` attributes, sentence-style names, `#[CoversClass]` never
   pointing at a tests/ class. Cover: resolved path, full query with every option, colon joining
   (AC #1), week 0, both booleans on/off, and the omission cases asserted via
   `queryParametersFor()` -- `http_build_query` drops nulls on its own, so the wire assertion alone
   lets the `array_filter` mutant escape Infection.
   - verify: `composer test` green offline (pure query tests, no fixtures needed yet)

4. **Record fixtures** with a throwaway `FixtureCaptureTest` asserting only `status() === 200`,
   run via `composer test:record -- --filter FixtureCapture`. Narrow the requests to keep fixtures
   small (FANTASY-3's first compare-players recording was 816KB): `limit=5` for news,
   `team_id=['SF']` + `include_probabilities: true` for injuries so the practice-report fields are
   actually populated. `ls -la tests/Fixtures/Saloon/nfl/` and re-record narrower if over ~50KB.
   Then read the JSON, diff it against the spec, and log every divergence to notes. Delete the
   capture test.

5. **DTOs**, modelled on the recorded payloads (spec shapes below are a hypothesis; fixtures are
   authoritative). `final readonly` under `src/Sdk/Data/`, hydrated through the existing `Payload`
   reader rather than hand-narrowing `mixed`.
   - `NewsFeed` (sport, title, description, count, `list<NewsItem>`, + `ApiLimits` if the tier
     fields are present) and `NewsItem`.
   - `InjuryReport` (sport, count, `list<NflInjury>`) and `NflInjury` -- base `Injury` fields plus
     the NFL-only block (status, status_short, ir_weeks, probability_of_playing, practice_1..3,
     practice_report_injury_type, team_practice_1..3_submitted).
   - `InjuryReport`/`NflInjury` are NFL-shaped on purpose, exactly as `PlayerComparison` is: MLB
     adds minor-league fields and drops the practice report, so a second sport gets its own DTO
     rather than a pile of nullable NFL fields here.
   - `status` stays a plain string on the DTO with a non-throwing `status(): ?NflInjuryStatus`
     accessor, mirroring `NflPlayer::position()` -- an unlisted status should not break an otherwise
     usable injury row. `practice_1..3` stay nullable strings (the spec's own set includes the
     sentinel `'--'`, and no accessor would benefit from an enum).
   - Likely `Payload` addition: `nullableFloat()` for `probability_of_playing`, which arrives as a
     numeric string (`'0.88797'`). Payload exists precisely to centralise this coercion; add a case
     to `tests/Sdk/Data/PayloadTest.php`.

6. **Hydration tests** (AC #2) via the `dtoFrom($request, 'nfl/news')` pattern from
   `ComparePlayersRequestTest::recordedComparison()`. Assert envelope fields, one item's every
   mapped field, and the edge rows the fixture actually contains (no rank, null practice value,
   `status()` returning both a known enum and null for an unrecognised code).

## Verification

`composer test`; `composer fmt && composer refactor && composer lint` (that order -- Rector and
Pint fight otherwise); `composer fmt:check && composer refactor:check`;
`./vendor/bin/deptrac analyse --config-file=deptrac.php` 0 violations; `composer test:mutate`
MSI >= 95. `FixtureSafetyTest` already scans every committed fixture for a leaked key and will
cover the two new ones automatically. Infection escapees get a real test, not an ignore -- only a
genuinely behaviour-neutral mutant earns a commented entry in `infection.json5`.

## Out of scope

MLB/NBA/NHL news and injury DTOs (structurally different payloads, separate tickets), the
`MLBAMID` news parameter (MLB-only), and committing -- the working tree stays dirty for user
review, as it has since FANTASY-1.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Recorded fixtures: spec-vs-reality divergences

Both endpoints recorded from the live API before any DTO was written (rotated key authenticates fine). Divergences the spec does not document:

1. **Both responses carry `limit`, `public_api_limited` and `tier`**, exactly like the FANTASY-3 payloads. Reused `ApiLimits` rather than re-deriving.
2. **Injuries truncates on the free tier the same way players does.** The in-season recording reports `count: 27` but returns 10 rows (`limit: 10`). `InjuryReport` therefore gets the same `truncated()` helper `PlayerCollection` has -- without it a caller silently believes it has the full injury list.
3. **`status` is not confined to the spec's enum -- an empty string is a real live value** (two of ten rows). Vindicates the plan's decision to keep `status` a plain string with a non-throwing `status(): ?NflInjuryStatus` accessor; `NflInjuryStatus::from()` would have thrown on a genuine payload.
4. **`probability_of_playing` is a numeric string, and `'0'` and `'1'` both occur.** Zero is meaningful (no chance of playing) and must not collapse to null, which is what drove `Payload::nullableFloat()`.
5. **`ir_weeks` is a list of ints** (`[8, 9, 10]` for a player on IR). `Payload` had `strings()` but no int-list reader, hence `Payload::ints()`.
6. **Undocumented top-level `covids` key** on injuries, an empty array in both recordings. A COVID-era vestige with no shape to model, so deliberately not mapped.
7. `practice_report_injury_type` is *either* null *or* an empty string, and `injury_type` is an empty string rather than absent when there is no injury. Both read through nullable accessors.
8. News items match the spec exactly, including the `created_formated` misspelling -- kept on the wire, corrected to `createdFormatted` on the DTO.

## Deviation: two injury fixtures, not one

The planned single recording was taken in August preseason, where `probability_of_playing`, all three `practice_*` fields and `practice_report_injury_type` are null for every row -- the entire NFL-only block of the DTO would have been untested. Recorded a second in-season fixture (`year=2025, week=10`) where those fields are populated. Kept both: `nfl/injuries` covers the all-null branches, `nfl/injuries-in-season` covers the populated ones and the free-tier truncation. 5.4KB and 6.5KB respectively.

## Review feedback applied (post-completion)

Two comments from the user's review of the staged FANTASY-1..4 diff:

1. **`ApiDataContract` interface** (`src/Sdk/Data/ApiDataContract.php`) declaring `public static function fromPayload(Payload $payload): self`, implemented by the seven item-level DTOs: `ApiLimits`, `ComparedExpert`, `ComparedPlayer`, `ExpertRank`, `NewsItem`, `NflInjury`, `NflPlayer`. The four envelope DTOs (`NewsFeed`, `InjuryReport`, `PlayerCollection`, `PlayerComparison`) are deliberately outside it -- they take a whole `Response` via `fromResponse()`, a different signature, and are hydrated by `createDtoFromResponse()` rather than by a parent DTO. Documented as such in the interface's docblock so the split is not mistaken for an oversight.

   Side effect worth knowing for future Infection runs: the mutant count dropped 202 -> 195, exactly seven. Infection cannot generate a `PublicVisibility` mutant for a method that implements an interface method, since narrowing the visibility would be a fatal error. One `fromPayload` per implementing class accounts for the difference; MSI stays 100% with no new escapees.

2. **Blank line between every enum case**, applied across all eleven enums in `src/Sdk/Enums/` (not just the two added by this ticket) so the style is uniform. Verified Pint preserves the blank lines rather than collapsing them, so `composer fmt` will not undo it.

Full gate re-run after both changes: 135 tests green, PHPStan level max clean, Pint/Rector clean, Deptrac 0 violations, Infection MSI 100%.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Both endpoints done. 135 tests green offline (up from 94), PHPStan level max clean, Rector/Pint clean, Deptrac 0 violations, Infection MSI 100%. Also verified green in record mode against the live API, which additionally runs FixtureSafetyTest's real-key scan over the three new fixtures.

**Method: recorded fixtures before DTOs, again.** Same sequence as FANTASY-3 -- enums and requests first with pure query tests, then capture real payloads, then model. It earned its keep: two of the divergences below would have produced a DTO that throws on genuine responses.

**AC #1 -- colon-delimited lists.** `GetInjuriesRequest` colon-joins both `team_id` (`list<string>`) and `player_ids` (`list<int>`). Asserted on the query repository via `queryParametersFor()`, not just the wire string -- `http_build_query` drops nulls on its own, so a URI-only assertion lets the `array_filter` mutant escape. Single-ID, multi-ID and empty-list cases each have a test.

**AC #2 -- fixtures hydrate.** `NewsFeed`/`NewsItem` and `InjuryReport`/`NflInjury` hydrate through `createDtoFromResponse()` off three recorded fixtures.

**Five spec-vs-reality divergences** (full list in the notes). The two that would have broken a spec-modelled DTO:
1. **`status` returns an empty string** for practice-report-only players -- not in the spec's eight-value enum. `NflInjuryStatus::from()` would have thrown on a real payload; `NflInjury` keeps the raw string and exposes a non-throwing `status(): ?NflInjuryStatus`, mirroring `NflPlayer::position()`.
2. **`injury_update_date` is null** for the same rows, despite the spec marking it required. Caught only because the fixture was read before the DTO was finalised.

Also: both responses carry the undocumented `limit`/`public_api_limited`/`tier` envelope (reused `ApiLimits`), injuries truncates on the free tier exactly as players does (`InjuryReport::truncated()`, 27 reported vs 10 returned), and injuries carries an undocumented empty `covids` array that is deliberately not modelled.

**Deviation from the plan: two injury fixtures, not one.** The planned single recording was taken in August preseason, where `probability_of_playing`, all three `practice_*` fields and `practice_report_injury_type` are null on every row -- the entire NFL-only half of `NflInjury` would have been asserted only as "null". Added an in-season recording (`year=2025, week=10`) that populates them. `nfl/injuries` now pins the absent-practice-report shape and `nfl/injuries-in-season` the populated one plus the truncation path. 5.4KB and 6.5KB; news is 4.7KB.

**Two `Payload` additions**, both forced by the payloads rather than anticipated:
- `nullableFloat()` for `probability_of_playing`, which arrives as a numeric string. `'0'` and `'1'` both occur live, and zero means no chance of playing -- distinct from the null the API sends off-season, so it must not collapse.
- `ints()` for `ir_weeks`. `Payload` had a string-list reader but no int-list reader.

**Design choices confirmed with the user before coding:** `include_minors`/`include_probabilities` are booleans mapping to the API's literal `'true'` or omission (the spec's "enum" has exactly one legal value, so presence is the signal -- same shape as `withPositionRank` -> `show=pos_rank`); `team_id` stays `list<string>` because the spec's `TeamId` schema has no `enum` block for `EnumsMatchSpecTest` to pin an invented `NflTeam` enum against.

`NewsCategory` lands here as FANTASY-2 deferred it. It and `NewsOrder` are inline parameter enums rather than `components.schemas` entries, so like `EcrFilter`/`ComparisonRankingType`/`ComparisonDetails` they are not covered by the spec-parity test; their values are pinned by the query-string tests.

**One Infection ignore added, documented in `infection.json5`:** `CastFloat` on `Payload::nullableFloat`. Verified equivalent rather than assumed -- PHP widens int to float through a `?float` return type even under `strict_types`, so the cast exists purely to satisfy PHPStan and no test can observe its removal. Every other mutant across the new code is killed by a real test.

**Not committed** -- working tree left dirty for review, as since FANTASY-1.
<!-- SECTION:FINAL_SUMMARY:END -->

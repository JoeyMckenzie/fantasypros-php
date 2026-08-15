---
id: FANTASY-15
title: 'Split src/Data into Api, Envelopes, Infrastructure and Contracts'
status: Done
assignee:
  - '@claude'
created_date: '2026-08-15 22:50'
updated_date: '2026-08-15 22:50'
labels:
  - sdk
  - refactor
milestone: SDK v0.1.0
dependencies:
  - FANTASY-12
modified_files:
  - src/Data/Api/
  - src/Data/Envelopes/
  - src/Data/Infrastructure/
  - src/Data/Contracts/
  - src/Requests/
  - tests/
  - deptrac.php
priority: medium
ordinal: 15000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
`src/Data` had grown to 20 flat classes mixing three different kinds of thing: DTOs hydrated from one decoded JSON object, envelopes hydrated from a whole `Response`, and the plumbing everything reads through. The flat namespace gave no signal about which was which, and `ApiDataContract`'s own docblock already documented a distinction the directory layout did not reflect.

Splitting the directory means every class in the layer declares what role it plays, and the boundary between roles becomes enforceable rather than conventional.

The split alone is not worth much without enforcement — Deptrac previously treated all of `Data` as a single layer, so any of the new folders could reach any other and nothing would notice. This task therefore covers both the move and the layering that makes it hold.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Every class under src/Data lives in one of Api, Envelopes, Infrastructure or Contracts, and its namespace matches its directory
- [x] #2 Deptrac enforces one-way dependencies between the four Data sub-layers rather than treating Data as a single layer
- [x] #3 The Deptrac rules are proven to catch a real violation, not just reported as green
- [x] #4 No behaviour changes: the suite passes with the same test and assertion counts as before the move
- [x] #5 The full gate passes: composer test, fmt:check, lint at PHPStan level max, and test:arch
<!-- AC:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## The layout

| Folder | Holds | Count |
|---|---|---|
| `Api/` | DTOs hydrated from one decoded JSON object via `fromPayload` | 11 |
| `Envelopes/` | DTOs a request hydrates from a whole `Response` via `fromResponse` | 7 |
| `Infrastructure/` | `Payload`, the `mixed`-taming accessor everything reads through | 1 |
| `Contracts/` | `ApiDataContract` | 1 |

## `ApiLimits` sits in `Api/`, and that placement is load-bearing

It was first put in `Infrastructure/` as quota metadata, which produced a **dependency cycle**: `ApiLimits` implements `ApiDataContract` (Infrastructure → Contracts) while `ApiDataContract::fromPayload(Payload $payload)` names `Payload` (Contracts → Infrastructure). No strict layering exists with that placement.

Moving `ApiLimits` to `Api/` resolves it at the source rather than working around it — it implements the contract and hydrates from a `Payload` exactly like every other DTO there. `Infrastructure/` is then `Payload` alone and becomes a true leaf. Do not move it back without re-reading this.

## Deptrac: Data is now four layers, and requests narrowed

Arrows run strictly inward, `envelopes -> api -> contracts -> infrastructure`. Two rules were tightened beyond the split because they reflect what the code actually does: `requests` may reach **only** `data.envelopes` (all seven requests hydrate an envelope and nothing else), and `data.infrastructure` may reach only `exceptions`.

Layer population was confirmed with `deptrac debug:layer` — envelopes 7, api 11, contracts 1, infrastructure 1, totalling the 20 files on disk — because a layer whose collector matches nothing also reports zero violations. A planted `contracts -> api` edge was correctly reported as `DependsOnDisallowedLayer` and then reverted, so the rules are exercised rather than vacuous. Allowed dependencies went 74 → 151.

## Gotcha: automated import rewriting matched docblock prose

The rewrite added imports by scanning for class names on a word boundary, which picked up names mentioned only in comments. That produced four unused imports, including `ApiDataContract` importing `InjuryReport` and `PlayerCollection` from the sentence explaining that envelopes are *not* part of the contract — an import that also pointed the dependency arrow backwards.

Neither PHPStan nor the Pint preset flags unused imports, so nothing in the gate would have caught them. They were found with a token-level scan that strips comments before matching. Worth repeating if imports are ever rewritten in bulk again.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
`src/Data`'s 20 flat classes are now split four ways — `Api/` (11), `Envelopes/` (7), `Infrastructure/` (1), `Contracts/` (1) — with each namespace matching its directory.

**No behaviour changed.** Every moved file was diffed against `HEAD` ignoring `namespace`/`use` lines and found byte-identical, and the suite reports the same 191 tests / 793 assertions as before. That equivalence is the evidence the move was pure.

**`ApiLimits` lives in `Api/`, not `Infrastructure/`, and that is deliberate.** Grouping it with `Payload` creates a genuine cycle: it implements `ApiDataContract` while the contract's signature names `Payload`. Moving it fixes the cycle at its cause and leaves `Infrastructure/` as a single-class leaf.

**Deptrac now enforces the split** — `envelopes -> api -> contracts -> infrastructure`, plus `requests` narrowed to envelopes only. Verified two ways rather than trusting a green report: `debug:layer` confirms all four layers are populated (7/11/1/1 = the 20 files on disk), and a planted `contracts -> api` edge was caught as `DependsOnDisallowedLayer`. 0 violations across 151 allowed dependencies.

Gate green: Pint, PHPStan level max, Deptrac, 191 tests.

One trap logged in the notes for anyone rewriting imports in bulk: matching class names on a word boundary also matches names in docblock prose, which silently produced four unused imports that no tool in the gate flags.
<!-- SECTION:FINAL_SUMMARY:END -->

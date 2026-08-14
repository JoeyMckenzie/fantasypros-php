---
id: FANTASY-12
title: Repoint the package as a standalone FantasyPros PHP SDK
status: Done
assignee:
  - '@claude'
created_date: '2026-08-14 21:38'
updated_date: '2026-08-14 22:10'
labels:
  - sdk
  - refactor
dependencies: []
modified_files:
  - composer.json
  - composer.lock
  - phpunit.xml
  - deptrac.php
  - infection.json5
  - CONTEXT.md
  - src/FantasyProsConnector.php
  - src/Data/
  - src/Enums/
  - src/Exceptions/
  - src/Requests/
  - tests/
priority: high
ordinal: 12000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The project is pivoting from a private "fantasy HQ" (SDK + MCP server in one repo) to a public, reusable FantasyPros API client for PHP, published on Packagist. There is no FantasyPros PHP SDK today, which is the reason for going public. The consumer that was going to be an MCP server becomes a separate Laravel Zero CLI in its own repository, so this repo carries the library and nothing else.

Nothing about the HTTP behaviour, DTOs or tests changes here. This is a rename and a strip: the package gets a public identity, the now-pointless `Sdk` namespace segment goes away, and the MCP scaffolding that was never built gets cleared out of the config and docs.

Agreed with the maintainer: Composer name `joeymckenzie/fantasypros-php`, autoload `FantasyPros\` -> `src/` (so today's `src/Sdk/*` flattens up one level and no class keeps an `Sdk` segment).

The MCP footprint is small because `src/Mcp` and `tests/Mcp` were never created. What actually references it: the `mcp` layer and its ruleset in `deptrac.php`, the `Mcp` testsuite in `phpunit.xml`, several sections of `CONTEXT.md`, and one docblock line in `src/Sdk/Requests/ComparePlayersRequest.php`.

Leave `devenv.nix` alone. Its `mcp` blocks configure the maintainer's editor tooling (Claude, Codex, opencode, plus FantasyPros' own hosted MCP endpoint) and have nothing to do with this package. The `MCP` mentions in `CLAUDE.md` and `AGENTS.md` are likewise Backlog.md's own tooling instructions.

One open decision for whoever picks this up: `deptrac.php` exists solely to enforce that `sdk` may not reach into `mcp`. With `mcp` gone there is one layer and no rule left, so decide whether to drop Deptrac from the toolchain or repoint it at a boundary that still means something inside the library, and say which in the task notes.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Composer package is named joeymckenzie/fantasypros-php with type "library" and a description that reflects its public purpose
- [x] #2 Autoload maps FantasyPros\ to src/, with no class remaining under an Sdk namespace segment or directory
- [x] #3 Test namespaces and directories follow the new root and the suite is green offline
- [x] #4 No reference to the MCP server remains in project config or docs (deptrac.php, phpunit.xml, CONTEXT.md, source docblocks)
- [x] #5 devenv.nix editor tooling and the Backlog.md sections of CLAUDE.md/AGENTS.md are left untouched
- [x] #6 CONTEXT.md describes a library-only project, including that the CLI consumer now lives in a separate repository
- [x] #7 The full gate passes: composer test, fmt:check, refactor:check, lint at PHPStan level max, and test:mutate at MSI >= 95
- [x] #8 The Deptrac decision (drop it or repoint it) is made and recorded in the task notes
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
## Decisions

| Question | Answer |
|---|---|
| Composer name | `joeymckenzie/fantasypros-php`, `type: library` |
| Root namespace | Flat `FantasyPros\` -> `src/`; `src/Sdk/*` flattens up one level, `Fantasy\Tests\` -> `FantasyPros\Tests\` over `tests/` |
| Deptrac (AC #8) | **Dropped.** Its only rule was `mcp` must not reach `sdk`; with one layer and no rule it enforces nothing, and a check that cannot fail is worse than no check. Remove the config, the dev dependency and the doc references. |
| Env var names | `FANTASY_FIXTURES` and `FANTASYPROS_API_KEY` unchanged -- not namespaced, and renaming would churn the harness for nothing. |

## Steps

1. **Move, preserving history**: `git mv src/Sdk/* src/` and `git mv tests/Sdk/* tests/`. Nothing in `tests/` collides -- there is no existing `tests/Data`, `tests/Requests` or `tests/Doubles`.

2. **Rewrite namespaces** across all 65 files carrying `Fantasy\`:
   - `Fantasy\Sdk\` -> `FantasyPros\`
   - `Fantasy\Tests\Sdk\` -> `FantasyPros\Tests\`
   - `Fantasy\Tests\` -> `FantasyPros\Tests\`
   Covers `namespace` declarations, `use` statements and any FQCN in strings.

3. **`composer.json`**: name, `type: library`, public-facing description, both autoload maps, and drop `deptrac/deptrac` from require-dev. Verify with `composer validate` and `composer dump-autoload`.

4. **`infection.json5`**: the mutator ignores are fully-qualified class names (`Fantasy\Sdk\Data\PlayerComparison::readRankings`, `Fantasy\Sdk\Data\RankedPlayer::readRanks`, `Fantasy\Sdk\Data\Payload::nullableFloat`) and will silently stop matching if not updated -- a silently-not-applied ignore would surface as new escaped mutants, so confirm MSI stays 100% after the rename.

5. **`phpunit.xml`**: the `Mcp` suite goes (AC #4; `tests/Mcp` never existed) and the `Sdk` suite's directory no longer exists after the flatten. Collapse to a single suite over `tests/`. No composer script filters by suite name, so nothing depends on the old names. `RequestTestCase.php`/`TestCase.php` are not picked up -- PHPUnit matches the `Test.php` suffix, which they do not have.

6. **Delete `deptrac.php`** per the decision above.

7. **`src/Requests/ComparePlayersRequest.php`**: the docblock explains a runtime guard as being for "MCP tool arguments". The guard stays -- it is right for any untrusted caller -- but the reason gets restated without reference to MCP.

8. **Rewrite `CONTEXT.md`** as a library-only document: what the package is, that it publishes to Packagist, that the CLI consumer lives in a separate Laravel Zero repo, and the current layout. Remove the two-layer architecture section, the `bin/fantasy-mcp` entrypoint, the MCP design phase and the Deptrac paragraph.

9. **Leave alone**: `devenv.nix` (editor tooling for Claude/Codex/opencode plus FantasyPros' own hosted MCP endpoint) and the Backlog.md sections of `CLAUDE.md`/`AGENTS.md`.

## Verification

`composer validate`; `composer test`; `composer fmt:check`; `composer refactor:check`; `composer lint`; `composer test:mutate` at MSI >= 95. Then grep the tree to prove no `Fantasy\Sdk`, `Fantasy\Tests` or project-MCP reference survives outside `devenv.nix` and the Backlog tooling blocks. Test and mutant counts should be unchanged from FANTASY-5's 191 tests / 288 mutants, since no behaviour changes here.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Deptrac: decision reversed mid-task, at the maintainer's request

The recorded plan said drop it, on the grounds that one layer with no rule enforces nothing. The maintainer pushed back with a concrete boundary worth having -- data classes must not depend on request classes, and nothing should depend on the connector -- so Deptrac stays with real internal rules instead.

Five layers, outermost first:

| Layer | May access |
|---|---|
| `connector` (`ClassLikeConfig` on `FantasyProsConnector`) | requests, data, enums, exceptions |
| `requests` | data, enums, exceptions |
| `data` | enums, exceptions |
| `enums` | nothing |
| `exceptions` | nothing |

The connector is matched by `ClassLikeConfig` rather than a directory, since it is a single file at the root of `src/`.

**Verified by planting violations, not by trusting a green run** -- the previous config's one rule was once wrongly suspected of being a no-op, so this got checked properly:
- `Data\ApiLimits` -> `Requests\GetPlayersRequest` correctly reported `DependsOnDisallowedLayer`.
- `Requests\GetPlayersRequest` -> `FantasyProsConnector` correctly reported `DependsOnDisallowedLayer`.
- Both reverted; back to 0 violations with 74 allowed dependencies, so the rules are exercised rather than vacuous.

## Rename mechanics worth knowing

The namespace rewrite needed three passes, because prefix rules with a trailing separator miss the forms that end in `;`:
1. `Fantasy\Sdk\` / `Fantasy\Tests\Sdk\` -> covered the `use` statements and FQCNs.
2. Bare `namespace Fantasy\Sdk;` and `namespace Fantasy\Tests;` -> missed by pass 1, fixed by hand.
3. `namespace Fantasy\Tests\Sdk;` -> pass 1's `Fantasy\Tests\` rule turned it into `FantasyPros\Tests\Sdk`, which autoloaded to nowhere. Caught by the suite failing with `Class "FantasyPros\Tests\RequestTestCase" not found`.

Also: `git mv` staged the previously-untracked files, so a later `git checkout` on one of them silently restored **pre-rename** content from the index. That reintroduced 36 PHPStan errors until spotted. Worth remembering while the tree stays uncommitted -- `git checkout <file>` here restores the index, not the working state you expect.

`tests/EnumsMatchSpecTest.php` needed its `SPEC_PATH` shortened from `../../docs` to `../docs` after moving up a directory. Nothing else depended on the old depth.

## Verified unchanged

191 tests / 793 assertions, 288 mutants, MSI 100% -- identical to FANTASY-5's numbers before the rename, which is the evidence that this was a pure rename and not a behaviour change. `composer test:record` also green against the live API, and all eight fixtures still parse as JSON (checked explicitly, since the rewrite touched every file matching `Fantasy\`).
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
The package is now `joeymckenzie/fantasypros-php`, a `library` with a flat `FantasyPros\` root over `src/`. No behaviour changed: 191 tests / 793 assertions, 288 mutants and MSI 100% are identical to the numbers before the rename, which is the point -- any difference would have meant the "pure rename" claim was false. PHPStan level max, Pint, Rector all clean; `composer test:record` green against the live API.

**What moved.** `src/Sdk/*` flattened into `src/` and `tests/Sdk/*` into `tests/`, via `git mv` so history follows. `Fantasy\Sdk\` -> `FantasyPros\`, `Fantasy\Tests\` -> `FantasyPros\Tests\` across 65 files, including the fully-qualified mutator ignores in `infection.json5` -- those would have silently stopped matching and shown up as new escaped mutants, so MSI staying at 100% is what confirms they still apply.

**What went.** The `Mcp` testsuite in `phpunit.xml` (`tests/Mcp` never existed; the three suites collapse to one over `tests/`), the MCP sections of `CONTEXT.md`, and the docblock line in `ComparePlayersRequest` that justified a runtime guard by reference to MCP tool arguments. The guard stays -- it is right for any caller building the list from user input -- with the reason restated in those terms.

**What stayed.** `devenv.nix` untouched: its `mcp` blocks configure the maintainer's editor tooling, including FantasyPros' own hosted MCP endpoint, and have nothing to do with this package. Same for the Backlog.md sections of `CLAUDE.md`/`AGENTS.md`. Env var names (`FANTASYPROS_API_KEY`, `FANTASY_FIXTURES`) are unchanged -- not namespaced, and renaming them would churn the harness for nothing.

**Deptrac: the plan said drop it, the maintainer said keep it, and the maintainer was right.** The reasoning for dropping was that one layer with no rule enforces nothing -- true of the old config, but the fix is a better config, not deletion. It now enforces five layers (`connector` -> `requests` -> `data` -> `enums`/`exceptions`), which encodes two real invariants: DTOs cannot reach back up into the requests that return them, and nothing depends on the connector, so requests and DTOs stay usable with any Saloon connector. Both rules were verified by planting actual violations and confirming Deptrac caught them, rather than trusting a green report -- 0 violations across 74 allowed dependencies.

**CONTEXT.md rewritten** as a library-only document, including a standing warning that the OpenAPI spec has diverged from the live payloads a dozen documented times across FANTASY-3/4/5, so fixtures get recorded and read before any DTO is modelled.

Two mechanics logged in the notes for whoever hits them next: prefix-based namespace rewrites miss the `namespace Foo\Bar;` forms that end in a semicolon, and `git mv` on untracked files stages them -- so `git checkout <file>` restores pre-rename content from the index rather than what you expect while this tree stays uncommitted.

**Not committed** -- working tree left dirty for review, as since FANTASY-1.
<!-- SECTION:FINAL_SUMMARY:END -->

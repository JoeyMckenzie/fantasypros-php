---
id: FANTASY-13
title: Publish the SDK to Packagist with a release pipeline
status: In Progress
assignee:
  - '@claude'
created_date: '2026-08-14 21:38'
updated_date: '2026-08-15 22:55'
labels:
  - sdk
  - release
milestone: SDK v0.1.0
dependencies:
  - FANTASY-12
  - FANTASY-8
  - FANTASY-14
  - FANTASY-16
priority: medium
ordinal: 13000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Make the package installable by the public with `composer require`, and keep it verifiable on every supported PHP version rather than only on the maintainer's machine.

The repo currently has no LICENSE file (composer.json declares MIT but nothing backs it), no CI workflow, and no Packagist registration. The full quality gate exists and passes locally -- tests against recorded Saloon fixtures, PHPStan level max with 100% type coverage, Pint, Rector, and Infection at MSI >= 95 -- but nothing runs it automatically, so a contributor or a PHP-version regression would go unnoticed.

The offline test suite needs no API key: `composer test` replays recorded fixtures and `MockConfig::throwOnMissingFixtures()` makes a missing one a hard failure rather than a silent live call. CI therefore needs no secret. `composer test:record` is the only thing that touches the network and must stay out of CI.

**Decision to make first: the minimum supported PHP version.** `composer.json` currently requires `^8.5` and `rector.php` runs the php85 set, which was fine for a private project pinned by devenv but is very restrictive for a public library -- 8.5 is new enough that almost nobody could install this. The language features actually in use put the real floor lower: `final readonly class` needs 8.2, and `#[Override]` plus typed class constants (`public const string`) need 8.3. Pick the floor deliberately, then make composer.json, rector.php and the CI matrix agree on it.

Usage documentation is deliberately not in scope here -- FANTASY-8 covers the README's install and per-endpoint examples, and this task depends on it so the package is not published without one.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A LICENSE file exists and matches the license composer.json declares
- [x] #2 The minimum supported PHP version is chosen and consistent across composer.json, rector.php and the CI matrix
- [ ] #3 CI runs the full gate (test, fmt:check, refactor:check, lint, test:mutate) on push and pull request across every supported PHP version
- [ ] #4 CI passes without any API key, and does not invoke composer test:record
- [x] #5 composer.json carries the metadata Packagist displays: description, keywords, homepage and support links
- [ ] #6 The package is registered on Packagist and installable via composer require at a tagged version
- [x] #7 The repository states how versions are tagged and what the release process is
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
## Decisions

| Question | Answer |
|---|---|
| Minimum PHP | **8.4**, per the maintainer: "most people using this package will likely be running at least 8.4 and I don't care about supporting 8.3 if it hinders what packages I can run with." Every version the package claims is one CI actually runs the whole suite on -- nothing is asserted that is not verified. |
| CI matrix | 8.4 and 8.5 |
| Docs | README usage examples stay in FANTASY-8; this task adds only what a release needs. |

`src/` itself would compile on 8.3 (its features top out at typed class constants and `#[Override]`), but PHPUnit 13.3 and symfony/yaml 8.1 both require >= 8.4.1, so an 8.3 claim could never be backed by a passing test run. 8.4 keeps the claim and the evidence aligned.

## Steps

1. **`LICENSE`** -- MIT, matching what composer.json already declares and the existing author metadata. Currently the declaration has nothing behind it.

2. **`composer.json`** -- bump `require.php` to `^8.4`; add the metadata Packagist renders: `keywords`, `homepage`, `support.issues`/`support.source`. Keep the existing description. Re-run `composer validate` (it enforces publish rules that `--no-check-publish` skips) and `composer update --lock`.

3. **`rector.php`** -- `withPhpSets(php85: true)` targets a version below the new floor's ceiling; retarget to php84 so Rector cannot rewrite code into syntax an 8.4 consumer rejects. Then re-run `composer refactor` and confirm the suite still passes, since this can legitimately change generated code.

4. **`.github/workflows/ci.yml`** -- matrix over PHP 8.4 and 8.5 running the full gate: `composer validate --strict`, `test`, `fmt:check`, `refactor:check`, `lint`, Deptrac, and `test:mutate`. Uses `shivammathur/setup-php`, `ramsey/composer-install` for caching, and Xdebug coverage only on the mutation job, which needs it. No secrets: the offline suite replays committed fixtures and `MockConfig::throwOnMissingFixtures()` turns a missing one into a hard failure, so CI can never silently reach the live API. `composer test:record` must not appear in any workflow.

5. **Release process** -- document tagging and SemVer in the README (or `CONTRIBUTING.md`), including that `test:record` is a maintainer-only step needing a real key, and that fixtures are committed deliberately.

6. **Packagist registration** -- requires the maintainer's own account and the GitHub webhook; cannot be done from here. Prepare everything up to that point, verify the package installs from a local path repository as a proxy for a real install, and hand the registration step back with exact instructions.

## Verification

`composer validate --strict` clean; the full gate green locally on 8.5; CI green on both 8.4 and 8.5 once pushed; a scratch project can `composer require` the package from a path repository and autoload `FantasyPros\FantasyProsConnector`. Note that CI cannot be proven green from this machine -- it needs a push -- so that AC closes only after the workflow runs.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Done: packaging and release readiness

- **`LICENSE`** -- MIT, matching the declaration composer.json already carried with nothing behind it.
- **PHP floor set to `^8.4`**, per the maintainer: most consumers will be on 8.4+, and supporting 8.3 is not worth constraining the dev toolchain. `rector.php` retargeted from the php85 set to php84 so Rector cannot rewrite code into syntax an 8.4 consumer would reject. Re-running `composer refactor` changed nothing, confirming the codebase was never using 8.5-only syntax.
- **Packagist metadata** -- keywords, homepage and support links. The URLs point at `github.com/JoeyMckenzie/fantasy`, the repo's actual remote. Note the mismatch with the package name `joeymckenzie/fantasypros-php`: Packagist does not require them to match, but renaming the repo to `fantasypros-php` would be tidier, and these URLs must be updated if that happens.
- **`CONTRIBUTING.md`** -- setup, the gate and the order it must run in, the Deptrac layering, the fixture policy (including the standing warning to record and read a fixture before modelling a DTO), and the SemVer/tagging release process. Usage documentation stays with FANTASY-8.
- **Lock file verified installable on 8.4**: no locked package requires 8.5; the highest floor in the tree is `>=8.4.1` (PHPUnit, symfony/*). So the 8.4 claim is not blocked by the dependency graph.

Gate green throughout: `composer validate --strict` clean, 191 tests, PHPStan level max, Pint, Rector, Deptrac 0 violations.

## Scope moved out: CI now belongs to FANTASY-14

A `shivammathur/setup-php` matrix workflow was written and then **deleted** at the maintainer's direction: CI here should be devenv-driven, matching the pattern in their website repo. That work is now FANTASY-14, and this task depends on it. Nothing exists under `.github/`.

ACs #3 and #4 (CI runs the gate on every supported version; CI needs no API key) are therefore not closable here -- they are FANTASY-14's ACs #1-#4. They are left unchecked rather than marked done, pending either that ticket landing or an explicit amendment to this one.

## Not done: publishing (AC #6)

The maintainer is handling Packagist registration and tagging themselves. Everything up to that point is in place. For the record, the remaining steps are: submit `https://github.com/JoeyMckenzie/fantasy` at packagist.org/packages/submit, add the GitHub service hook so tags sync automatically, then `git tag -a v0.1.0 -m "v0.1.0" && git push origin v0.1.0`.

Worth deciding before the first tag: the endpoint set is incomplete (FANTASY-6 covers projections and player-points, FANTASY-7 typed error handling), so a `0.x` first tag leaves room to change the public surface without a major bump.

## Update after FANTASY-15/16/17 (2026-08-15)

**The repo rename happened.** The earlier note flagged that `composer.json`'s URLs pointed at `github.com/JoeyMckenzie/fantasy` while the package is named `joeymckenzie/fantasypros-php`, and that the URLs would need updating if the repo were renamed. The maintainer has since renamed it — `homepage` and both `support` links now read `fantasypros-php`. Confirm the GitHub remote and the Packagist submission URL match before tagging.

**A `test:arch` composer script was added** for Deptrac. The gate is now: `test`, `fmt:check`, `refactor:check`, `lint`, `test:arch`, `test:mutate`.

**The public surface changed shape, which matters for the first tag.** Consumers now call endpoint methods on the connector (`$connector->players(...)`) rather than constructing Requests and calling `->dto()`. That is the API being published, so the README (FANTASY-8) must teach it, and this task now depends on FANTASY-16.

The argument for a `0.x` first tag is stronger than before: the endpoint set is still incomplete (FANTASY-6), typed error handling is not in place (FANTASY-7), and every future endpoint adds a method to the connector's public surface. A `0.x` line leaves room for that without a major bump each time.
<!-- SECTION:NOTES:END -->

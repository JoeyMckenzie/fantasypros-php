---
id: FANTASY-13
title: Publish the SDK to Packagist with a release pipeline
status: In Progress
assignee:
  - '@claude'
created_date: '2026-08-14 21:38'
updated_date: '2026-08-14 22:11'
labels:
  - sdk
  - release
dependencies:
  - FANTASY-12
  - FANTASY-8
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
- [ ] #1 A LICENSE file exists and matches the license composer.json declares
- [ ] #2 The minimum supported PHP version is chosen and consistent across composer.json, rector.php and the CI matrix
- [ ] #3 CI runs the full gate (test, fmt:check, refactor:check, lint, test:mutate) on push and pull request across every supported PHP version
- [ ] #4 CI passes without any API key, and does not invoke composer test:record
- [ ] #5 composer.json carries the metadata Packagist displays: description, keywords, homepage and support links
- [ ] #6 The package is registered on Packagist and installable via composer require at a tagged version
- [ ] #7 The repository states how versions are tagged and what the release process is
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

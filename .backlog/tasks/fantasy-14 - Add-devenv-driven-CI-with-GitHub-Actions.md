---
id: FANTASY-14
title: Add devenv-driven CI with GitHub Actions
status: To Do
assignee: []
created_date: '2026-08-14 22:17'
updated_date: '2026-08-15 22:55'
labels:
  - sdk
  - ci
milestone: SDK v0.1.0
dependencies: []
priority: high
ordinal: 14000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Nothing runs the quality gate automatically today. It exists and passes locally -- offline PHPUnit against recorded Saloon fixtures, PHPStan level max with 100% type coverage over `src`, `tests` and `examples`, Pint, Rector, Deptrac's layering, and Infection at MSI >= 95 -- but a contributor's PR or a dependency bump could break any of it unnoticed. This package is going public, so "it passes on the maintainer's machine" stops being good enough.

**Use devenv as the driver, matching the pattern in the maintainer's website repo** (`../website/.github/workflows/ci.yml`). That workflow does not use `shivammathur/setup-php` at all. Each job installs Nix (`cachix/install-nix-action`), restores the shared devenv Cachix cache (`cachix/cachix-action` with `name: devenv`), installs devenv via `nix profile install nixpkgs#devenv`, and then runs a single named devenv script -- `devenv shell ci-lint`, `devenv shell ci-test`. The environment CI runs in is therefore the same one the developer shell provides, so a green CI run means the toolchain genuinely agrees rather than two configurations happening to line up.

Conventions worth carrying over from that workflow: jobs split by concern rather than one long job, `actions/checkout` pinned to a commit SHA with the version in a trailing comment, `persist-credentials: false` on checkout, `permissions: contents: read` at the top, and triggers on push to `main` plus pull requests. The website repo also has a `.github/dependabot.yml` keeping the GitHub Actions ecosystem current on a weekly schedule with grouped updates and a cooldown -- worth mirroring, since pinned-by-SHA actions otherwise go stale silently.

**Deptrac now has a `composer test:arch` script**, so CI invokes that rather than calling the binary directly. Its layering has also grown since this ticket was written: the old five layers are now eight, because `data` was split into `data.envelopes -> data.api -> data.contracts -> data.infrastructure` (FANTASY-15) and the `connector` layer collects `src/Concerns` alongside the connector class (FANTASY-16). Nothing about how CI runs it changes; the description is corrected so nobody hunts for five layers.

`devenv.nix` already defines `ci-lint` (fmt:check, refactor:check, lint) and `ci-test` (test). It does **not** yet cover Deptrac or Infection, both of which are part of the gate, so new scripts are needed. Infection needs Xdebug for coverage; the devenv PHP config already enables the `xdebug` extension, so this should work without extra setup, but confirm rather than assume.

**No secrets are needed and none should be added.** `composer test` is fully offline: it replays committed fixtures, and `MockConfig::throwOnMissingFixtures()` turns a missing fixture into a hard failure rather than a silent live API call. `composer test:record` is the only thing that touches the network and must never appear in a workflow -- if it ran in CI it would need a real key and would rewrite fixtures. The `examples/` scripts also hit the live API and must not run in CI either; they are covered statically by PHPStan, which is enough.

**Decision to make first: how CI covers the supported PHP range.** FANTASY-13 sets `require.php` to `^8.4`, so the package claims 8.4 and 8.5. But `devenv.nix` pins `languages.php.version = "8.5"`, so a straightforwardly devenv-driven workflow verifies 8.5 only, leaving the 8.4 claim untested. Three ways out, and this ticket should pick one deliberately rather than let the gap sit unnoticed:
1. Parameterise the devenv PHP version so the same scripts run across a matrix.
2. Run the full devenv gate on the pinned version and add one lightweight non-devenv job that runs just the test suite on 8.4.
3. Narrow `require.php` to `^8.5` so the claim matches what CI actually proves -- consistent, but cuts the package's reach.

A previous attempt at this task wrote a `shivammathur/setup-php` matrix workflow; it was deleted rather than kept, because it was the wrong driver and would have been misleading as a starting point. Nothing under `.github/` exists now.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 CI runs on push to main and on pull requests, driven by devenv shell scripts rather than setup-php, following the pattern in ../website/.github/workflows/ci.yml
- [ ] #2 devenv.nix defines scripts covering the whole gate, including the Deptrac and Infection steps it does not cover today
- [ ] #3 The chosen approach to covering both supported PHP versions is implemented, and any version composer.json claims but CI cannot verify is either covered or removed from the claim
- [ ] #4 CI passes with no repository secrets configured, and no workflow invokes composer test:record or runs the examples/ scripts
- [ ] #5 actions/checkout is pinned to a commit SHA with persist-credentials disabled, and the workflow declares least-privilege permissions
- [ ] #6 Dependabot keeps the GitHub Actions ecosystem updated on a schedule, mirroring the website repo's configuration
- [ ] #7 A CI run is green end to end on a real push or pull request, not only reasoned about locally
<!-- AC:END -->

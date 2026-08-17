---
id: FANTASY-18
title: Extract a shared foundation for the fantasyphp PHP API clients
status: To Do
assignee: []
created_date: '2026-08-17 22:09'
labels:
  - architecture
  - shared-package
dependencies: []
references:
  - 'https://github.com/fantasyphp/fantasypros-php'
  - 'https://github.com/fantasyphp/myfantasyleague-php'
documentation:
  - 'https://docs.saloon.dev/llms.txt'
  - 'https://devenv.sh/composing-using-imports/'
priority: medium
ordinal: 18000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The fantasyphp org currently has two Saloon-backed PHP API clients — fantasypros-php and myfantasyleague-php — and a third (Sleeper) is planned. A file-by-file diff of the two existing repos found substantial duplication that will get worse with each new client:

- Eight files are byte-identical across both repos: devenv.yaml, pint.json, cliff.toml, release.sh, .github/workflows/ci.yml, .github/dependabot.yml, AGENTS.md, CLAUDE.md. Five more (devenv.nix, phpstan.neon, rector.php, phpunit.xml, deptrac.php) differ only by a project name or a single path.
- Several PHP classes are identical apart from their namespace: ApiDataContract, UnexpectedPayloadException, ApiRequestException, RateLimitException, tests/bootstrap.php, and the per-client marker interface (FantasyProsRequestException / MflRequestException share the same six-paragraph docblock verbatim).
- The record/replay test harness (FixtureMode, TestCase, RequestTestCase, FixtureSafetyTest, HarnessTest, StubRequest) is ~450 lines of the same design in both repos.
- Connector setup repeats the same retry policy, timeouts and Saloon plugin set; nine FantasyPros request classes and one MyFantasyLeague request base each repeat the same array_filter null-dropping idiom.

The goal is to stop hand-copying this into a third and fourth client. Two new repositories are proposed: fantasyphp/devenv (a devenv module consumed as a flake input) and fantasyphp/client-core (a Composer package). They stay separate because a nix-only change should not bump a Packagist version, and because client-core's own devenv.nix would otherwise import out of its own repo.

Notably out of scope for now: the Payload class. It looks like the most duplicated file but is the most dangerous to share — see the subtask covering that decision.

This is a tracking parent. The work is in the subtasks.
<!-- SECTION:DESCRIPTION:END -->

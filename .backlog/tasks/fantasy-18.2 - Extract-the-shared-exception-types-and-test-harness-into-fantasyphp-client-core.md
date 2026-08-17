---
id: FANTASY-18.2
title: >-
  Extract the shared exception types and test harness into
  fantasyphp/client-core
status: In Progress
assignee:
  - '@claude'
created_date: '2026-08-17 22:09'
updated_date: '2026-08-17 23:13'
labels:
  - php
  - shared-package
  - testing
  - exceptions
dependencies: []
documentation:
  - 'https://docs.saloon.dev/llms.txt'
  - 'https://docs.saloon.dev/the-basics/handling-failures'
  - 'https://docs.saloon.dev/testing/recording-responses'
parent_task_id: FANTASY-18
priority: high
ordinal: 20000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Creates the fantasyphp/client-core Composer package and moves the first tranche of genuinely shared PHP into it — roughly 600 lines currently duplicated across fantasypros-php and myfantasyleague-php.

What is duplicated today:

Exceptions. UnexpectedPayloadException (32 lines) and ApiDataContract (23 lines) are identical apart from the namespace. ApiRequestException and RateLimitException are the same class shape. The marker interfaces FantasyProsRequestException and MflRequestException carry the same six-paragraph docblock with one word changed — including the notes on why it is an interface rather than a base class (Pint's final_class rule) and why each implementation must extend Saloon's RequestException (handleRetry is typed FatalRequestException|RequestException).

Test harness. FixtureMode, TestCase, RequestTestCase, FixtureSafetyTest, HarnessTest, StubRequest and tests/bootstrap.php implement the same record/replay fixture design in both repos, with the same environment-variable isolation and the same guard against a fixture capturing a credential. MyFantasyLeague additionally has FixtureNormalizer (recursive key sorting for non-deterministic payload key order), which is not FantasyPros-specific and belongs in the shared package too.

Two design constraints worth carrying over:

Each client must keep its own marker interface, extending a shared one, so existing `catch (MflRequestException)` call sites keep working while a consumer using two of these packages can catch across all of them with one type.

FixtureSafetyTest and HarnessTest are real test cases that must execute in each consumer, so they need to ship as abstract base cases that each client subclasses, not as concrete tests in a vendor directory.

The package is required normally for the runtime classes and dev-only for the harness.

Repos: /Users/joeymckenzie/code/github.com/joeymckenzie/fantasy and ../myfantasyleague.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 A fantasyphp/client-core Composer package exists with its own test suite and the same lint/static-analysis gates as the client repos
- [ ] #2 client-core provides the shared payload/data contract and unexpected-payload exception types, and both clients consume them instead of their local copies
- [ ] #3 client-core provides a shared marker interface for failed API responses, and each client's own marker interface extends it
- [ ] #4 Existing catch sites for each client's marker interface continue to work unchanged, and a single catch of the shared interface catches failures from both clients
- [ ] #5 The shared exception classes still satisfy Saloon's handleRetry type union, verified by a test that a failed request surfaces the client's own exception type
- [ ] #6 The record/replay harness lives in client-core and is required dev-only by both clients
- [ ] #7 Fixture-safety and harness self-checks ship as abstract cases that both clients subclass, and both suites still execute those checks
- [ ] #8 The fixture key-sorting normaliser is available to both clients from client-core
- [ ] #9 Both clients' suites pass offline against their existing committed fixtures with no fixture re-recording
- [ ] #10 Mutation testing and Deptrac still pass in both clients after the move
- [ ] #11 client-core has a README documenting what it provides and how a new client wires it up
<!-- AC:END -->

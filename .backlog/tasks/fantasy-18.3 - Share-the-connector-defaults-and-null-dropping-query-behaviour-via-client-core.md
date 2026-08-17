---
id: FANTASY-18.3
title: Share the connector defaults and null-dropping query behaviour via client-core
status: To Do
assignee: []
created_date: '2026-08-17 22:10'
updated_date: '2026-08-17 22:10'
labels:
  - php
  - shared-package
  - saloon
  - connector
dependencies:
  - FANTASY-18.2
documentation:
  - 'https://docs.saloon.dev/llms.txt'
  - 'https://docs.saloon.dev/digging-deeper/retrying-requests'
  - 'https://docs.saloon.dev/the-basics/connectors'
parent_task_id: FANTASY-18
priority: medium
ordinal: 21000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Moves the repeated Saloon connector setup and the repeated query-building idiom into fantasyphp/client-core, once that package exists.

Connector setup. FantasyProsConnector and MflConnector independently declare the same Saloon plugin set (AcceptsJson, AlwaysThrowOnErrors, HasTimeout), the same retry configuration (three tries, 500ms interval, exponential backoff), the same 10s connect / 30s request timeouts, and the same getRequestException delegation to a per-client failure mapper.

What must stay per-client, and is the reason this is a trait rather than a base connector:

- Retry policy genuinely differs. FantasyPros retries 429; MyFantasyLeague deliberately does not, because MFL's documentation says to slow down rather than retry a throttled request, so its 429 surfaces immediately as a RateLimitException.
- Failure detection differs. MyFantasyLeague overrides hasRequestFailed because MFL reports application errors as an HTTP 200 with an `error` key in the body, so status alone decides nothing. FantasyPros has no such override.
- Authentication differs. FantasyPros uses an x-api-key header authenticator and refuses to construct without a key; MyFantasyLeague has no connector-level credential and only sets an optional User-Agent.
- Base URL construction differs. FantasyPros is a constant; MyFantasyLeague templates the league year into the host path.
- The failure mappers (each client's RequestFailure) share only their role, not their implementation — one maps HTTP status to exception, the other sniffs an error body and pattern-matches the message. Do not attempt to unify these.

Query building. The idiom `array_filter($query, static fn ($value): bool => $value !== null)` — dropping nulls so an omitted parameter is not sent as an empty one — appears in all nine FantasyPros request classes and once in MyFantasyLeague's ExportRequest base.

Repos: /Users/joeymckenzie/code/github.com/joeymckenzie/fantasy and ../myfantasyleague. Depends on client-core existing.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 client-core provides a connector trait carrying the shared Saloon plugin set, retry counts, backoff configuration and timeouts, with the failure mapping left as a per-client extension point
- [ ] #2 Both connectors use the trait and no longer declare those shared defaults themselves
- [ ] #3 FantasyPros still retries 429 and MyFantasyLeague still does not, each covered by a test asserting the retry decision for a throttled response
- [ ] #4 MyFantasyLeague still treats a 200 response carrying an error body as a failure, and FantasyPros is unaffected by that behaviour
- [ ] #5 Each client's authentication and base-URL behaviour is unchanged, including FantasyPros refusing to construct with a blank API key
- [ ] #6 client-core provides a reusable way to drop null query parameters, and the nine FantasyPros request classes plus MyFantasyLeague's ExportRequest use it instead of their own array_filter
- [ ] #7 Parameters whose value is a meaningful zero or empty-but-intentional value are still sent, covered by a regression test
- [ ] #8 Both clients' suites pass offline against existing fixtures, and the resolved request URLs in the endpoint tests are unchanged
- [ ] #9 Mutation testing and Deptrac still pass in both clients
<!-- AC:END -->

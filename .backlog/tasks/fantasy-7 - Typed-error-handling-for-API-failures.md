---
id: FANTASY-7
title: Typed error handling for API failures
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
updated_date: '2026-08-15 22:55'
labels:
  - sdk
milestone: SDK v0.1.0
dependencies:
  - FANTASY-2
priority: medium
ordinal: 7000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Map API failures to typed exceptions: 401/403 auth, 400 validation (spec returns {message, parameter, valid_format}), 429 rate limit. Wire Saloon's shouldThrowRequestException / custom RequestException resolution on the connector.

**The failure surface is now the connector's endpoint methods** (FANTASY-16). Consumers call `$connector->players(...)` and never touch Saloon, so a raw `Saloon\Exceptions\Request\Statuses\TooManyRequestsException` leaking out of a domain method is exactly the abstraction leak this ticket exists to close. The typed exceptions belong in `src/Exceptions`, which every layer may reach.

**Observed live, worth encoding rather than guessing at:**

- **429** returns `{"message":"Limit Exceeded"}` and surfaces today as `Saloon\Exceptions\Request\Statuses\TooManyRequestsException`. Reproduced by exhausting the free tier.
- **403** was seen once transiently and could not be reproduced afterwards on the same key, so its body shape is unconfirmed — capture it rather than assuming it matches the documented 400 shape.
- `FantasyProsConnector::handleRetry` already retries 429 and 5xx with three tries and exponential backoff, and deliberately does not retry 401/400. Typed exceptions must not change that: a retried-then-failed 429 should still arrive as the rate-limit type.

Note the quota tiers differ in behaviour, not just volume: the free tier truncates responses (`ApiLimits::$limited`, `limit`, and the envelopes' `truncated()`), while the paid tier returns full sets and fails outright at the daily cap. Truncation is not an error and must stay out of the exception mapping.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Each failure class has a MockClient test asserting the thrown exception type and message content
- [ ] #2 Failures surfaced through the connector's endpoint methods throw FantasyPros exception types, not raw Saloon ones
- [ ] #3 The 429 mapping preserves the existing retry behaviour in handleRetry
- [ ] #4 Response truncation on limited tiers is not treated as an error
- [ ] #5 The full gate passes: composer test, fmt:check, refactor:check, lint, test:arch
<!-- AC:END -->

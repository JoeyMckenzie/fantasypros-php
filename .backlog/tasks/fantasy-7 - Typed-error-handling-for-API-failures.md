---
id: FANTASY-7
title: Typed error handling for API failures
status: Done
assignee:
  - '@claude'
created_date: '2026-08-14 06:16'
updated_date: '2026-08-15 23:59'
labels:
  - sdk
milestone: SDK v0.1.0
dependencies:
  - FANTASY-2
modified_files:
  - src/Exceptions/FantasyProsRequestException.php
  - src/Exceptions/RequestFailure.php
  - src/Exceptions/AuthenticationException.php
  - src/Exceptions/RateLimitException.php
  - src/Exceptions/ValidationException.php
  - src/Exceptions/ApiRequestException.php
  - src/FantasyProsConnector.php
  - tests/Exceptions/RequestFailureTest.php
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
- [x] #1 Each failure class has a MockClient test asserting the thrown exception type and message content
- [x] #2 Failures surfaced through the connector's endpoint methods throw FantasyPros exception types, not raw Saloon ones
- [x] #3 The 429 mapping preserves the existing retry behaviour in handleRetry
- [x] #4 Response truncation on limited tiers is not treated as an error
- [x] #5 The full gate passes: composer test, fmt:check, refactor:check, lint, test:arch
<!-- AC:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Probing the live API contradicted most of the ticket's premises

The description asked for "401/403 auth, 400 validation, 429 rate limit". Probing found only **two** of those statuses exist:

| Probe | Result |
|---|---|
| Wrong key | `403 {"message":"Forbidden"}` |
| Empty key | `403 {"message":"Forbidden"}` |
| No key header at all | `403 {"message":"Forbidden"}` |
| Unknown route | `403 {"message":"Missing Authentication Token"}` |
| `position=BOGUS` | **200**, value silently ignored |
| `week=notanumber` | **200**, value silently ignored |
| `players=abc,def` (violates the documented pattern) | **200**, value silently ignored |
| Omitting `position`, which the spec marks required | **200**, defaulted |
| `season=notayear` | **200**, defaulted to the current season |

**401 is never returned** -- every authentication failure is a 403, and the three causes are indistinguishable from outside. **400 is unreachable**: no parameter abuse produced one, because the API coerces or ignores bad input rather than rejecting it. The 403 the ticket recorded as "seen once transiently, shape unconfirmed" is simply the ordinary auth failure.

Both are still mapped -- the ticket asked for them and the spec documents the 400 body -- but `ValidationException` and the 401 arm are marked in their own docblocks as unreachable against the API as it stands, so nobody mistakes a caught `ValidationException` for a routine outcome.

## An interface, not a base class, and Pint decided that

The first cut was an abstract-ish base `FantasyProsRequestException` with three subclasses. Pint immediately marked it `final` (`final_class`) and Rector privatised its shared property (`protected_to_private`), producing `Class X extends final class Y` and an undefined-property access -- 18 PHPStan errors.

Those are deliberate rules in `pint.json`, not accidents: **this codebase does not subclass its own types.** Rather than carve out an exception, the design moved to what the config was pushing toward -- a marker interface with four final classes, each extending Saloon's `RequestException` and implementing `FantasyProsRequestException`. Mapping lives in a separate final `RequestFailure`.

A side effect worth having: dropping the shared base also dropped the `apiMessage()` accessor that motivated it. The API's own text is already folded into `getMessage()` ("FantasyPros returned 403: Forbidden"), so nothing was lost but the storage problem.

## Extending Saloon's RequestException is load-bearing

`HasTries::handleRetry(FatalRequestException|RequestException $exception, Request $request)` receives whatever `getRequestException()` returned. A mapped type outside that union would be a **TypeError before the connector could decide whether to retry** -- silently disabling retries for exactly the transient failures they exist to absorb. This is why every mapped class extends `RequestException` rather than, say, `RuntimeException`, and it is pinned by a test that catches as `Throwable` and asserts both the Saloon type and the marker interface.

## The retry tests were sleeping, and it was hiding four bugs

The first passing version ran the suite in **8.2s**, up from 1.5s, because the retry tests slept through the real 500ms interval with exponential backoff. Infection then reported **56 mutants "required more time than configured"** -- counted as killed, so MSI read 100%.

Killing mutants by timeout is not killing them. Overriding `retryInterval = 0` on a test-local connector (the real interval is still asserted in `FantasyProsConnectorTest`) took the suite back to **1.6s** and dropped the timeouts to zero -- which exposed **four genuinely unkilled mutants the timeouts had been masking**:

1. `readString`'s `&&` → `||`. Killed by a data provider covering a `message` that is empty, a number, and an object -- the non-string cases are a type error under the mutant.
2. and 3. `ValidationException`'s `int $code = 0` → `1` / `-1`. Killed by asserting `getCode()`.
4. `SupportsPlayerEndpoints::players()`'s `$withPositionRank = false` → `true`. This one was **pre-existing but uncovered** until these tests started calling the endpoint method; covering it without asserting it turned an uncovered mutant into an escaped one. Killed by asserting the method sends no query beyond the sport -- which also pins FANTASY-16's rule that endpoint methods invent no defaults.

MSI back to **100%, 0 escaped, 0 timed out**.

## Truncation stays out of the mapping

A limited tier answers 200, so `$response->failed()` is false and nothing throws -- no code was needed for AC #4, only a test proving it. `RateLimitException`'s docblock points at `ApiLimits` and `truncated()` so the distinction is findable from the exception a caller is holding.

## Body parsing avoids a Deptrac cycle

`RequestFailure` reads the body with plain `json_decode` rather than through `Payload`. `Payload` throws `UnexpectedPayloadException` from the `exceptions` namespace, so reading through it would point `exceptions -> data.infrastructure` and close a cycle -- the same trap FANTASY-15 hit with `ApiLimits`. `debug:layer exceptions` confirms all nine classes are collected rather than sitting outside the rules.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Every API failure now arrives as a FantasyPros type. Consumers catch the `FantasyProsRequestException` interface for all of them, or `AuthenticationException` / `RateLimitException` / `ValidationException` / `ApiRequestException` for a specific cause. **238 tests / 1064 assertions**, PHPStan level max clean, Deptrac 0 violations, Infection **MSI 100%**.

**Probing the live API contradicted most of what the ticket assumed.** Only two of the four documented statuses exist:

- **401 is never returned.** A wrong key, an empty key and no key header at all all answer `403 {"message":"Forbidden"}` — indistinguishable from outside. The 403 the ticket logged as "seen once, shape unconfirmed" is just the ordinary auth failure.
- **400 is unreachable.** Bogus enum values, malformed colon lists, a non-numeric season, and omitting a parameter the spec marks required *all* answer 200 with the value silently ignored. The API coerces bad input rather than rejecting it.

Both are still mapped as the ticket asked, but each is documented in place as unreachable so a caught `ValidationException` reads as "the API changed", not "routine".

**Pint chose the design.** A base class with subclasses drew 18 PHPStan errors the moment `final_class` and `protected_to_private` ran — those rules mean this codebase doesn't subclass its own types. Restructured to a marker interface with four final classes and a separate `RequestFailure` mapper, which is what the config was pushing toward.

**Extending Saloon's `RequestException` is load-bearing, not cosmetic.** `handleRetry` is typed `FatalRequestException|RequestException` and receives whatever `getRequestException()` returns, so a type outside that union would TypeError before the connector could decide whether to retry — silently disabling retries for the transient failures they exist to absorb. Pinned by test.

**The retry tests were sleeping, and it was hiding four bugs.** Real 500ms backoff pushed the suite 1.5s → 8.2s, and Infection reported 56 mutants "requiring more time than configured" — counted as killed, so MSI read a false 100%. Zeroing the interval in tests (the real value is still asserted separately) took the suite back to **1.6s** and exposed four genuinely unkilled mutants, including one *pre-existing* gap in `players()`'s default that these tests newly covered without asserting. All four killed with real tests.

Truncation needed no code — a limited tier answers 200, so nothing throws — only a test proving it stays out of the mapping.
<!-- SECTION:FINAL_SUMMARY:END -->

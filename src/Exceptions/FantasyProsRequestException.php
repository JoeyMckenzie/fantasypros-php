<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

/**
 * A failed FantasyPros response, as one type the caller can catch.
 *
 * Consumers reach the API through the connector's endpoint methods and never
 * touch Saloon, so a raw `Saloon\Exceptions\Request\Statuses\*` leaking out of
 * `$connector->players(...)` would be an abstraction leak. Every HTTP failure
 * implements this instead: catch it to catch all of them, or one of the
 * implementing classes to catch a specific cause.
 *
 * **An interface rather than a base class, on purpose.** Pint enforces
 * `final_class` and `protected_to_private` across this codebase, so a shared
 * parent would be finalised out from under its own subclasses. Marking the
 * shape instead leaves every implementation final, which is the house style.
 *
 * Each implementation also extends Saloon's `RequestException`, and that part
 * is load-bearing: the retry loop calls `handleRetry(FatalRequestException|
 * RequestException $exception, ...)` with whatever `getRequestException()`
 * returned. A type outside that union would be a TypeError before the connector
 * could decide whether the failure was worth another attempt, silently
 * disabling retries for exactly the transient failures they exist to absorb.
 */
interface FantasyProsRequestException {}

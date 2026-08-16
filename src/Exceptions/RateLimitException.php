<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * The account's request quota is exhausted.
 *
 * Carries `{"message":"Limit Exceeded"}`. Reaching this means every retry was
 * already spent: the connector treats 429 as transient and makes three attempts
 * with exponential backoff, so this is the outcome after the last one failed.
 *
 * Not the same thing as response truncation, which isn't an error at all. A
 * limited tier answers 200 and quietly returns fewer players than it counts;
 * `ApiLimits` and the envelopes' `truncated()` cover that.
 */
final class RateLimitException extends RequestException implements FantasyProsRequestException {}

<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * A failure with no more specific mapping: a 404, a 5xx, anything unforeseen.
 *
 * Here so nothing reaches a consumer as a raw Saloon type. Any status the SDK
 * doesn't name explicitly still arrives implementing
 * `FantasyProsRequestException`.
 */
final class ApiRequestException extends RequestException implements FantasyProsRequestException {}

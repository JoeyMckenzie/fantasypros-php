<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * A failure with no more specific mapping -- a 404, a 5xx, anything unforeseen.
 *
 * Exists so that nothing reaches a consumer as a raw Saloon type: every status
 * the SDK does not name explicitly still arrives implementing
 * `FantasyProsRequestException`.
 */
final class ApiRequestException extends RequestException implements FantasyProsRequestException {}

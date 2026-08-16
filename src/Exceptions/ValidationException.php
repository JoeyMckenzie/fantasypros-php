<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;
use Throwable;

/**
 * The API rejected a parameter.
 *
 * The spec documents a 400 body of `{message, parameter, valid_format}`, and
 * this maps it. **No request has been found that provokes one.** Bogus enum
 * values, malformed colon-delimited lists, a non-numeric season and omitting a
 * parameter the spec marks required all answer 200 with the value silently
 * ignored. Treat a caught `ValidationException` as evidence the API changed
 * rather than as a routine outcome, and prefer validating arguments before
 * sending -- as `InvalidComparisonException` does.
 */
final class ValidationException extends RequestException implements FantasyProsRequestException
{
    /**
     * @param  ?string  $parameter  the parameter the API named as invalid
     * @param  ?string  $validFormat  the format it says that parameter accepts
     */
    public function __construct(
        Response $response,
        ?string $message = null,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $parameter = null,
        public readonly ?string $validFormat = null,
    ) {
        parent::__construct($response, $message, $code, $previous);
    }
}

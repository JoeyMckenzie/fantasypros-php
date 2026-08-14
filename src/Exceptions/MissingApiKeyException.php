<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use RuntimeException;

/**
 * Raised when the SDK is handed no usable API key.
 *
 * Deliberately thrown while building the connector rather than left to surface
 * as a 401 on the first request, so a misconfigured environment fails at the
 * point the mistake was made.
 */
final class MissingApiKeyException extends RuntimeException
{
    public static function blank(): self
    {
        return new self('The FantasyPros API key cannot be empty.');
    }

    public static function absentFromEnvironment(string $variable): self
    {
        return new self(sprintf(
            'No FantasyPros API key found. Set %s in your environment or .env file; '
            .'request a key at https://secure.fantasypros.com/api-keys/request/.',
            $variable,
        ));
    }
}

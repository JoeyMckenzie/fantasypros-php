<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a player comparison is built with arguments the endpoint cannot
 * act on, caught before the request is sent.
 */
final class InvalidComparisonException extends InvalidArgumentException
{
    public static function withoutPlayers(): self
    {
        return new self('Comparing players needs at least one FantasyPros player ID.');
    }
}

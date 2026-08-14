<?php

declare(strict_types=1);

namespace Fantasy\Tests;

use Fantasy\Sdk\FantasyProsConnector;
use RuntimeException;

/**
 * How this run of the suite treats Saloon fixtures.
 *
 * The same tests run in both modes. Offline they replay recorded fixtures;
 * in record mode a missing fixture is fetched from the live API and written
 * to disk, so fixtures stay real payloads rather than hand-written guesses.
 */
enum FixtureMode: string
{
    /**
     * Fixtures must already exist. Nothing touches the network.
     */
    case Strict = 'strict';

    /**
     * Missing fixtures are recorded from the live API using the real key.
     */
    case Record = 'record';

    public const string ENV_VAR = 'FANTASY_FIXTURES';

    public const string API_KEY_VAR = FantasyProsConnector::API_KEY_ENV_VAR;

    /**
     * Stand-in key for offline runs, where no request leaves the process.
     */
    public const string OFFLINE_API_KEY = 'offline-test-key';

    public static function current(): self
    {
        $mode = self::env(self::ENV_VAR);

        if ($mode === null) {
            return self::Strict;
        }

        return self::tryFrom($mode) ?? throw new RuntimeException(sprintf(
            '%s must be one of "%s", got "%s".',
            self::ENV_VAR,
            implode('", "', array_column(self::cases(), 'value')),
            $mode,
        ));
    }

    /**
     * The key the connector under test should authenticate with.
     */
    public function apiKey(): string
    {
        if ($this === self::Strict) {
            return self::OFFLINE_API_KEY;
        }

        return self::env(self::API_KEY_VAR) ?? throw new RuntimeException(sprintf(
            'Recording fixtures needs a real API key. Set %s in .env.',
            self::API_KEY_VAR,
        ));
    }

    private static function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}

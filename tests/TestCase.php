<?php

declare(strict_types=1);

namespace Fantasy\Tests;

use Fantasy\Sdk\FantasyProsConnector;
use Override;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Base case that isolates the environment variables this project reads.
 *
 * Both the fixture mode and the API key are resolved from `$_SERVER`, `$_ENV`
 * and the process environment, so a test that pokes at one has to restore all
 * three or it leaks into the next test.
 */
abstract class TestCase extends PhpUnitTestCase
{
    private const array MANAGED_VARS = [
        FixtureMode::ENV_VAR,
        FantasyProsConnector::API_KEY_ENV_VAR,
    ];

    /**
     * @var array<array-key, mixed>
     */
    private array $originalServer;

    /**
     * @var array<array-key, mixed>
     */
    private array $originalEnv;

    /**
     * @var array<string, string|false>
     */
    private array $originalProcessEnv;

    #[Override]
    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalEnv = $_ENV;
        $this->originalProcessEnv = [];

        foreach (self::MANAGED_VARS as $name) {
            $this->originalProcessEnv[$name] = getenv($name);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_ENV = $this->originalEnv;

        foreach ($this->originalProcessEnv as $name => $value) {
            $value === false ? putenv($name) : putenv($name.'='.$value);
        }
    }

    /**
     * Clear a variable from every source the project reads.
     */
    final protected function forgetVar(string $name): void
    {
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);
    }

    final protected function setVar(string $name, string $value): void
    {
        $_SERVER[$name] = $value;
        putenv($name.'='.$value);
    }
}

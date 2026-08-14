<?php

declare(strict_types=1);

namespace FantasyPros\Tests;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\MockConfig;

/**
 * Covers the test harness itself, not src/, so it declares no CoversClass --
 * FixtureMode lives in tests/ and is not a valid coverage target.
 */
final class HarnessTest extends TestCase
{
    #[Test]
    public function the_suite_defaults_to_offline_mode(): void
    {
        $this->forgetVar(FixtureMode::ENV_VAR);

        self::assertSame(FixtureMode::Strict, FixtureMode::current());
    }

    #[Test]
    public function an_unrecognised_mode_is_rejected(): void
    {
        $this->setVar(FixtureMode::ENV_VAR, 'sometimes');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FANTASY_FIXTURES must be one of "strict", "record", got "sometimes".');

        FixtureMode::current();
    }

    #[Test]
    public function offline_mode_never_needs_a_real_key(): void
    {
        self::assertSame(FixtureMode::OFFLINE_API_KEY, FixtureMode::Strict->apiKey());
    }

    #[Test]
    public function record_mode_refuses_to_run_without_a_key(): void
    {
        $this->forgetVar(FixtureMode::API_KEY_VAR);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Recording fixtures needs a real API key. Set FANTASYPROS_API_KEY in .env.');

        FixtureMode::Record->apiKey();
    }

    #[Test]
    public function record_mode_uses_the_key_from_the_environment(): void
    {
        $this->setVar(FixtureMode::API_KEY_VAR, 'a-real-looking-key');

        self::assertSame('a-real-looking-key', FixtureMode::Record->apiKey());
    }

    #[Test]
    public function record_mode_picks_up_the_key_from_the_dotenv_file(): void
    {
        if (FixtureMode::current() !== FixtureMode::Record) {
            self::markTestSkipped('Only record runs load .env.');
        }

        self::assertNotSame('', FixtureMode::Record->apiKey());
    }

    #[Test]
    public function offline_runs_fail_on_a_missing_fixture_rather_than_calling_the_api(): void
    {
        if (FixtureMode::current() !== FixtureMode::Strict) {
            self::markTestSkipped('Recording deliberately allows missing fixtures.');
        }

        self::assertTrue(MockConfig::isThrowingOnMissingFixtures());
    }

    #[Test]
    public function fixtures_are_stored_under_the_tests_directory(): void
    {
        self::assertSame(
            __DIR__.'/Fixtures/Saloon',
            MockConfig::getFixturePath(),
        );
    }

    #[Test]
    public function the_fantasy_namespace_maps_to_the_src_directory(): void
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);

        self::assertInstanceOf(ClassLoader::class, $loader);

        $prefixes = $loader->getPrefixesPsr4();

        self::assertArrayHasKey('FantasyPros\\', $prefixes);
        self::assertSame(
            [realpath(__DIR__.'/../src')],
            array_map(realpath(...), $prefixes['FantasyPros\\']),
        );
    }
}

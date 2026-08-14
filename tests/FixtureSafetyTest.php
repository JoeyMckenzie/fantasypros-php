<?php

declare(strict_types=1);

namespace FantasyPros\Tests;

use FantasyPros\FantasyProsConnector;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Saloon\MockConfig;
use SplFileInfo;

/**
 * Guards the committed fixtures against carrying the API key.
 *
 * Saloon structurally cannot write a request header into a fixture -- a
 * RecordedResponse holds only the status, response headers, body and context.
 * The residual risk this covers is the response side: a gateway that echoes
 * x-api-key back, or a payload that quotes it.
 */
final class FixtureSafetyTest extends TestCase
{
    #[Test]
    public function no_fixture_carries_an_api_key_header(): void
    {
        $fixtures = $this->fixtures();

        self::assertNotSame([], $fixtures, 'Expected recorded fixtures to guard.');

        foreach ($fixtures as $path => $contents) {
            $decoded = json_decode($contents, true);

            self::assertIsArray($decoded, sprintf('%s is not valid JSON.', $path));
            self::assertArrayHasKey('headers', $decoded, sprintf('%s has no headers block.', $path));
            self::assertIsArray($decoded['headers']);

            foreach (array_keys($decoded['headers']) as $header) {
                self::assertDoesNotMatchRegularExpression(
                    '/api[-_]?key/i',
                    (string) $header,
                    sprintf('%s recorded an API-key-ish response header.', $path),
                );
            }
        }
    }

    #[Test]
    public function no_fixture_contains_the_live_api_key(): void
    {
        if (FixtureMode::current() !== FixtureMode::Record) {
            self::markTestSkipped('The real key is only loaded when recording.');
        }

        $apiKey = FixtureMode::Record->apiKey();

        foreach ($this->fixtures() as $path => $contents) {
            self::assertStringNotContainsString(
                $apiKey,
                $contents,
                sprintf('%s contains the live API key.', $path),
            );
        }
    }

    #[Test]
    public function fixtures_never_record_the_request_side(): void
    {
        foreach ($this->fixtures() as $path => $contents) {
            $decoded = json_decode($contents, true);

            self::assertIsArray($decoded);
            self::assertSame(
                ['statusCode', 'headers', 'data', 'context'],
                array_keys($decoded),
                sprintf('%s has an unexpected fixture shape.', $path),
            );
        }
    }

    #[Test]
    public function the_connectors_key_header_is_the_one_being_guarded(): void
    {
        // Pins the regex above to the header the connector actually sends, so a
        // rename cannot silently narrow what these tests check.
        self::assertMatchesRegularExpression('/api[-_]?key/i', FantasyProsConnector::API_KEY_HEADER);
    }

    /**
     * @return array<string, string>
     */
    private function fixtures(): array
    {
        $root = MockConfig::getFixturePath();

        if (! is_dir($root)) {
            return [];
        }

        $fixtures = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        ) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $path = $file->getPathname();
            $contents = file_get_contents($path);

            self::assertIsString($contents);

            $fixtures[$path] = $contents;
        }

        return $fixtures;
    }
}

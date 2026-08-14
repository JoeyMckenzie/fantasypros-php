<?php

declare(strict_types=1);

namespace FantasyPros\Tests;

use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Pins the enums to the OpenAPI spec itself rather than to a list copied out of
 * it, so a spec update that adds a value fails here instead of silently
 * dropping out of the SDK.
 */
#[CoversClass(Sport::class)]
#[CoversClass(NflPosition::class)]
#[CoversClass(NflRankingType::class)]
#[CoversClass(NflScoringType::class)]
final class EnumsMatchSpecTest extends TestCase
{
    private const string SPEC_PATH = __DIR__.'/../docs/fantasypros-open-api-spec-v2.yml';

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function specSchemas(): iterable
    {
        yield 'Sport' => ['Sport', Sport::class];
        yield 'NFLPositions' => ['NFLPositions', NflPosition::class];
        yield 'NFLRankingTypes' => ['NFLRankingTypes', NflRankingType::class];
        yield 'NFLScoringTypes' => ['NFLScoringTypes', NflScoringType::class];
    }

    /**
     * @param  class-string<Sport|NflPosition|NflRankingType|NflScoringType>  $enum
     */
    #[Test]
    #[DataProvider('specSchemas')]
    public function the_enum_covers_every_value_in_the_spec(string $schema, string $enum): void
    {
        $expected = $this->specEnumValues($schema);
        $actual = array_column($enum::cases(), 'value');

        sort($expected);
        sort($actual);

        self::assertSame($expected, $actual, sprintf(
            '%s has drifted from the %s schema in the spec.',
            $enum,
            $schema,
        ));
    }

    #[Test]
    public function the_sport_path_segment_is_lowercased_for_the_route(): void
    {
        self::assertSame('nfl', Sport::Nfl->pathSegment());
        self::assertSame('ncaaf', Sport::Ncaaf->pathSegment());
    }

    /**
     * Pull one schema's `enum` values out of the spec.
     *
     * Duplicates are collapsed: NFLRankingTypes lists PRO and PROSPECT twice,
     * and a PHP enum cannot hold duplicate cases.
     *
     * @return list<string>
     */
    private function specEnumValues(string $schema): array
    {
        $spec = Yaml::parseFile(self::SPEC_PATH);

        self::assertIsArray($spec);
        self::assertArrayHasKey('components', $spec);
        self::assertIsArray($spec['components']);
        self::assertArrayHasKey('schemas', $spec['components']);
        self::assertIsArray($spec['components']['schemas']);
        self::assertArrayHasKey($schema, $spec['components']['schemas']);

        $definition = $spec['components']['schemas'][$schema];

        self::assertIsArray($definition);
        self::assertArrayHasKey('enum', $definition);
        self::assertIsArray($definition['enum']);

        $values = [];

        foreach ($definition['enum'] as $value) {
            self::assertIsString($value);

            $values[$value] = $value;
        }

        return array_values($values);
    }
}

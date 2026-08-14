<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Data;

use FantasyPros\Data\Payload;
use FantasyPros\Exceptions\UnexpectedPayloadException;
use FantasyPros\FantasyProsConnector;
use FantasyPros\Tests\Doubles\StubRequest;
use FantasyPros\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(Payload::class)]
#[CoversClass(UnexpectedPayloadException::class)]
final class PayloadTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, ?int}>
     */
    public static function integerCandidates(): iterable
    {
        yield 'int' => [42, 42];
        yield 'negative int' => [-7, -7];
        yield 'numeric string' => ['2026', 2026];
        yield 'negative numeric string' => ['-7', -7];
        yield 'zero string' => ['0', 0];
        yield 'trailing letters' => ['12abc', null];
        yield 'leading letters' => ['abc12', null];
        yield 'decimal string' => ['1.5', null];
        yield 'padded string' => [' 12', null];
        yield 'empty string' => ['', null];
        yield 'bool' => [true, null];
        yield 'array' => [[1], null];
        yield 'null' => [null, null];
    }

    /**
     * @return iterable<string, array{mixed, ?float}>
     */
    public static function floatCandidates(): iterable
    {
        yield 'decimal string' => ['0.27468', 0.27468];
        // The endpoint sends both bounds as bare digits, and zero means no
        // chance of playing rather than no opinion.
        yield 'zero string' => ['0', 0.0];
        yield 'one string' => ['1', 1.0];
        yield 'float' => [0.5, 0.5];
        yield 'int' => [1, 1.0];
        yield 'letters' => ['likely', null];
        yield 'empty string' => ['', null];
        yield 'bool' => [true, null];
        yield 'array' => [[0.5], null];
        yield 'null' => [null, null];
    }

    /**
     * @return iterable<string, array{callable(Payload, string): array<array-key, mixed>}>
     */
    public static function mapReaders(): iterable
    {
        yield 'intMap' => [static fn (Payload $payload, string $key): array => $payload->intMap($key)];
        yield 'floatMap' => [static fn (Payload $payload, string $key): array => $payload->floatMap($key)];
        yield 'stringMap' => [static fn (Payload $payload, string $key): array => $payload->stringMap($key)];
        yield 'boolMap' => [static fn (Payload $payload, string $key): array => $payload->boolMap($key)];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonTrueValues(): iterable
    {
        yield 'string true' => ['true'];
        yield 'one' => [1];
        yield 'string one' => ['1'];
        yield 'false' => [false];
        yield 'null' => [null];
    }

    #[Test]
    public function it_reads_a_string(): void
    {
        self::assertSame('Saquon', Payload::of(['name' => 'Saquon'])->string('name'));
    }

    /**
     * The API is inconsistent about quoting numbers, so a numeric value read as
     * a string is coerced rather than rejected.
     */
    #[Test]
    public function it_coerces_numbers_to_strings(): void
    {
        self::assertSame('17240', Payload::of(['id' => 17240])->string('id'));
        self::assertSame('1.5', Payload::of(['id' => 1.5])->string('id'));
    }

    #[Test]
    public function a_missing_string_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "name" to be a string, got null.');

        Payload::of([])->string('name');
    }

    #[Test]
    public function a_non_string_value_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "name" to be a string, got array.');

        Payload::of(['name' => ['nested']])->string('name');
    }

    #[Test]
    public function a_nullable_string_tolerates_absence(): void
    {
        self::assertNull(Payload::of([])->nullableString('name'));
        self::assertNull(Payload::of(['name' => ['nested']])->nullableString('name'));
    }

    #[Test]
    #[DataProvider('integerCandidates')]
    public function it_reads_integers_strictly(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, Payload::of(['rank' => $value])->nullableInt('rank'));
    }

    #[Test]
    public function a_non_integer_value_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "rank" to be an integer, got string.');

        Payload::of(['rank' => 'first'])->int('rank');
    }

    /**
     * Only a literal JSON `true` counts, so a "false"-ish string is not truthy.
     */
    #[Test]
    #[DataProvider('nonTrueValues')]
    public function only_a_real_boolean_true_is_true(mixed $value): void
    {
        self::assertFalse(Payload::of(['limited' => $value])->bool('limited'));
    }

    #[Test]
    public function it_reads_a_true_boolean(): void
    {
        self::assertTrue(Payload::of(['limited' => true])->bool('limited'));
    }

    #[Test]
    #[DataProvider('floatCandidates')]
    public function it_reads_floats_from_numeric_strings(mixed $value, ?float $expected): void
    {
        self::assertSame($expected, Payload::of(['probability' => $value])->nullableFloat('probability'));
    }

    #[Test]
    public function it_reads_every_item_of_an_integer_list(): void
    {
        self::assertSame([8, 9, 10], Payload::of(['weeks' => [8, 9, 10]])->ints('weeks'));
    }

    #[Test]
    public function a_missing_integer_list_is_empty(): void
    {
        self::assertSame([], Payload::of([])->ints('weeks'));
    }

    #[Test]
    public function a_non_integer_item_in_a_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "weeks" to be a list of integers, got string.');

        Payload::of(['weeks' => [8, '9']])->ints('weeks');
    }

    #[Test]
    public function it_reads_every_item_of_a_string_list(): void
    {
        self::assertSame(
            ['STD', 'PPR', 'HALF'],
            Payload::of(['ranks' => ['STD', 'PPR', 'HALF']])->strings('ranks'),
        );
    }

    #[Test]
    public function a_missing_string_list_is_empty(): void
    {
        self::assertSame([], Payload::of([])->strings('ranks'));
    }

    #[Test]
    public function a_non_string_item_in_a_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "ranks" to be a list of strings, got int.');

        Payload::of(['ranks' => ['STD', 7]])->strings('ranks');
    }

    #[Test]
    public function it_reads_a_map_of_integers(): void
    {
        self::assertSame(
            ['ALL' => 178, 'QB' => 185],
            Payload::of(['accuracy' => ['ALL' => 178, 'QB' => 185]])->intMap('accuracy'),
        );
    }

    /**
     * Ranks arrive as numeric strings in some maps and ints in others.
     */
    #[Test]
    public function a_map_of_integers_takes_numeric_strings(): void
    {
        self::assertSame([1091 => 6, 2791 => 9], Payload::of(['experts' => ['1091' => '6', '2791' => 9]])->intMap('experts'));
    }

    #[Test]
    public function it_reads_a_map_of_numbers_mixing_integers_and_decimals(): void
    {
        self::assertSame(
            ['RB' => 8.0, 'FLX' => 8.7],
            Payload::of(['ECR_AVG' => ['RB' => 8, 'FLX' => 8.7]])->floatMap('ECR_AVG'),
        );
    }

    #[Test]
    public function it_reads_a_map_of_strings(): void
    {
        self::assertSame(
            ['QB' => '2025-09-04 20:02:55'],
            Payload::of(['positions' => ['QB' => '2025-09-04 20:02:55']])->stringMap('positions'),
        );
    }

    /**
     * Same rule as bool(): only a literal JSON true counts.
     */
    #[Test]
    public function it_reads_a_map_of_booleans(): void
    {
        self::assertSame(
            ['QB' => true, 'RB' => false, 'WR' => false],
            Payload::of(['default' => ['QB' => true, 'RB' => false, 'WR' => 'true']])->boolMap('default'),
        );
    }

    /**
     * @param  callable(Payload, string): array<array-key, mixed>  $read
     */
    #[Test]
    #[DataProvider('mapReaders')]
    public function a_missing_map_is_empty(callable $read): void
    {
        self::assertSame([], $read(Payload::of([]), 'absent'));
        self::assertSame([], $read(Payload::of(['absent' => 'not a map']), 'absent'));
    }

    #[Test]
    public function a_map_entry_of_the_wrong_type_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "accuracy" to be a map of integers, got string.');

        Payload::of(['accuracy' => ['ALL' => 'unranked']])->intMap('accuracy');
    }

    #[Test]
    public function a_string_map_entry_of_the_wrong_type_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "positions" to be a map of strings, got array.');

        Payload::of(['positions' => ['QB' => ['nested']]])->stringMap('positions');
    }

    #[Test]
    public function a_number_map_entry_of_the_wrong_type_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "ranks" to be a map of numbers, got bool.');

        Payload::of(['ranks' => ['RB' => true]])->floatMap('ranks');
    }

    #[Test]
    public function it_reads_every_object_of_a_list(): void
    {
        $objects = Payload::of(['items' => [['id' => 1], ['id' => 2], ['id' => 3]]])->objects('items');

        self::assertCount(3, $objects);
        self::assertSame([1, 2, 3], array_map(static fn (Payload $item): int => $item->int('id'), $objects));
    }

    #[Test]
    public function a_missing_object_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "items" to be an array, got null.');

        Payload::of([])->objects('items');
    }

    #[Test]
    public function a_scalar_item_in_an_object_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "items" to be an array of objects, got string.');

        Payload::of(['items' => ['nope']])->objects('items');
    }

    #[Test]
    public function it_reads_every_entry_of_an_object_map(): void
    {
        $map = Payload::of(['by_id' => ['17240' => ['n' => 1], '_0' => ['n' => 2]]])->objectMap('by_id');

        // PHP casts the numeric key to an int; the sentinel key stays a string.
        self::assertSame([17240, '_0'], array_keys($map));
        self::assertSame(2, $map['_0']->int('n'));
    }

    #[Test]
    public function a_missing_object_map_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "by_id" to be an object, got null.');

        Payload::of([])->objectMap('by_id');
    }

    #[Test]
    public function a_scalar_entry_in_an_object_map_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "by_id" to be an object of objects, got int.');

        Payload::of(['by_id' => ['17240' => 5]])->objectMap('by_id');
    }

    #[Test]
    public function it_exposes_its_own_keys_and_membership(): void
    {
        $payload = Payload::of(['a' => 1, 17240 => 2, 'c' => null]);

        self::assertSame(['a', '17240', 'c'], $payload->keys());
        self::assertTrue($payload->has('a'));
        // has() is isset-based, so an explicit null reads as absent.
        self::assertFalse($payload->has('c'));
        self::assertFalse($payload->has('missing'));
    }

    #[Test]
    public function a_non_object_response_body_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected the response body to be a JSON object.');

        Payload::fromResponse($this->responseWithBody('"just a string"'));
    }

    #[Test]
    public function it_reads_an_object_response_body(): void
    {
        $payload = Payload::fromResponse($this->responseWithBody('{"sport":"NFL"}'));

        self::assertSame('NFL', $payload->string('sport'));
    }

    private function responseWithBody(string $body): \Saloon\Http\Response
    {
        return new FantasyProsConnector('secret-key')->send(
            new StubRequest,
            new MockClient([MockResponse::make($body)]),
        );
    }
}

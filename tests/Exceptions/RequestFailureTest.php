<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Exceptions;

use FantasyPros\Enums\Sport;
use FantasyPros\Exceptions\ApiRequestException;
use FantasyPros\Exceptions\AuthenticationException;
use FantasyPros\Exceptions\FantasyProsRequestException;
use FantasyPros\Exceptions\RateLimitException;
use FantasyPros\Exceptions\RequestFailure;
use FantasyPros\Exceptions\ValidationException;
use FantasyPros\FantasyProsConnector;
use FantasyPros\Tests\Doubles\StubRequest;
use FantasyPros\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Throwable;

#[CoversClass(RequestFailure::class)]
#[CoversClass(ApiRequestException::class)]
#[CoversClass(AuthenticationException::class)]
#[CoversClass(RateLimitException::class)]
#[CoversClass(ValidationException::class)]
#[CoversClass(FantasyProsConnector::class)]
final class RequestFailureTest extends TestCase
{
    /**
     * Statuses with no mapping of their own still have to arrive as a
     * FantasyPros type rather than a Saloon one.
     *
     * @return iterable<string, array{int}>
     */
    public static function unmappedStatuses(): iterable
    {
        yield 'not found' => [404];
        yield 'server fault' => [500];
        yield 'bad gateway' => [502];
    }

    /**
     * Both statuses the SDK treats as an auth failure. Only 403 actually shows
     * up: a wrong key, an empty key and no key header at all all answer with
     * it.
     *
     * @return iterable<string, array{int}>
     */
    public static function authenticationStatuses(): iterable
    {
        yield 'forbidden' => [403];
        yield 'unauthorized' => [401];
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function unusableMessages(): iterable
    {
        yield 'empty string' => [['message' => '']];
        yield 'a number' => [['message' => 503]];
        yield 'an object' => [['message' => ['nested' => 'thing']]];
    }

    #[Test]
    #[DataProvider('authenticationStatuses')]
    public function a_rejected_key_is_an_authentication_failure(int $status): void
    {
        $exception = $this->failureFrom(MockResponse::make(['message' => 'Forbidden'], $status));

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame(sprintf('FantasyPros returned %d: Forbidden', $status), $exception->getMessage());
        self::assertSame($status, $exception->getStatus());
    }

    #[Test]
    public function an_exhausted_quota_is_a_rate_limit_failure(): void
    {
        // Three, because the connector retries a 429 twice before giving up.
        $exception = $this->failureFrom(
            MockResponse::make(['message' => 'Limit Exceeded'], 429),
            MockResponse::make(['message' => 'Limit Exceeded'], 429),
            MockResponse::make(['message' => 'Limit Exceeded'], 429),
        );

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame('FantasyPros returned 429: Limit Exceeded', $exception->getMessage());
    }

    /**
     * The 400 shape the spec documents. Nothing has been found that provokes
     * one, since bad parameters get silently ignored and answered with a 200,
     * so this is built from the documented body rather than a recorded one.
     */
    #[Test]
    public function a_rejected_parameter_is_a_validation_failure(): void
    {
        $exception = $this->failureFrom(MockResponse::make([
            'message' => 'Invalid parameter',
            'parameter' => 'players',
            'valid_format' => '^(\d+)((?:\:\d+)+)?$',
        ], 400));

        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertSame('FantasyPros returned 400: Invalid parameter', $exception->getMessage());
        self::assertSame('players', $exception->parameter);
        self::assertSame('^(\d+)((?:\:\d+)+)?$', $exception->validFormat);
        // The API sends no error code of its own, so the exception carries none.
        self::assertSame(0, $exception->getCode());
    }

    #[Test]
    public function a_validation_failure_without_the_optional_fields_still_maps(): void
    {
        $exception = $this->failureFrom(MockResponse::make(['message' => 'Invalid parameter'], 400));

        self::assertInstanceOf(ValidationException::class, $exception);
        self::assertNull($exception->parameter);
        self::assertNull($exception->validFormat);
    }

    #[Test]
    #[DataProvider('unmappedStatuses')]
    public function an_unmapped_failure_is_still_a_fantasypros_type(int $status): void
    {
        // A 5xx is retried, so queue enough responses for every attempt.
        $exception = $this->failureFrom(
            MockResponse::make(['message' => 'Something broke'], $status),
            MockResponse::make(['message' => 'Something broke'], $status),
            MockResponse::make(['message' => 'Something broke'], $status),
        );

        // The fallback type rather than one of the named causes, still
        // carrying the marker interface, which is the point of AC #2.
        self::assertInstanceOf(FantasyProsRequestException::class, $exception);
        self::assertInstanceOf(ApiRequestException::class, $exception);
        self::assertSame(sprintf('FantasyPros returned %d: Something broke', $status), $exception->getMessage());
    }

    /**
     * Without a `message` field there is nothing better to say than what Saloon
     * already composes from the status and body, so the default is kept rather
     * than replaced with a worse invented one.
     */
    #[Test]
    public function a_failure_carrying_no_message_keeps_saloons_description(): void
    {
        $exception = $this->failureFrom(MockResponse::make(['detail' => 'nothing useful'], 403));

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertStringContainsString('Forbidden (403)', $exception->getMessage());
    }

    #[Test]
    public function a_failure_with_a_non_object_body_still_maps(): void
    {
        $exception = $this->failureFrom(MockResponse::make('plain text, not json', 403));

        self::assertInstanceOf(AuthenticationException::class, $exception);
    }

    /**
     * A `message` that's present but unusable (empty, or not a string at all)
     * counts as absent rather than getting forced into the message. The second
     * case would be a type error if the guard were loosened, which is why both
     * are here.
     *
     * @param  array<array-key, mixed>  $body
     */
    #[Test]
    #[DataProvider('unusableMessages')]
    public function an_unusable_message_falls_back_to_saloons_description(array $body): void
    {
        $exception = $this->failureFrom(MockResponse::make($body, 403));

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertStringContainsString('Forbidden (403)', $exception->getMessage());
    }

    /**
     * AC #2. The failure has to arrive typed when it comes out of a domain
     * method, which is the only surface a consumer of this SDK touches.
     */
    #[Test]
    public function a_failure_from_an_endpoint_method_is_typed(): void
    {
        $connector = new FantasyProsConnector('secret-key');
        $connector->withMockClient(new MockClient([
            MockResponse::make(['message' => 'Forbidden'], 403),
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('FantasyPros returned 403: Forbidden');

        $connector->players(Sport::Nfl);
    }

    /**
     * The mapped types stay inside Saloon's `RequestException` hierarchy on
     * purpose. `handleRetry` is typed `FatalRequestException|RequestException`,
     * so an exception outside that union would be a TypeError before the
     * connector could decide whether to retry, silently disabling retries for
     * the transient failures they exist to absorb.
     */
    #[Test]
    public function the_mapped_types_remain_catchable_as_saloon_request_exceptions(): void
    {
        $mockClient = new MockClient([MockResponse::make(['message' => 'Forbidden'], 403)]);

        // Caught as Throwable rather than through the helper, so the narrowing
        // below is the assertion doing the work rather than a return type.
        try {
            new FantasyProsConnector('secret-key')->send(new StubRequest, $mockClient);
            self::fail('The request was expected to fail.');
        } catch (Throwable $throwable) {
            self::assertInstanceOf(RequestException::class, $throwable);
            self::assertInstanceOf(FantasyProsRequestException::class, $throwable);
            self::assertSame(403, $throwable->getResponse()->status());
        }
    }

    /**
     * AC #3. Mapping must not have changed which failures are worth repeating:
     * a 429 is still attempted three times, and still arrives as the rate-limit
     * type after the last one fails.
     */
    #[Test]
    public function a_rate_limit_is_still_retried_before_it_is_raised(): void
    {
        $mockClient = new MockClient([
            MockResponse::make(['message' => 'Limit Exceeded'], 429),
            MockResponse::make(['message' => 'Limit Exceeded'], 429),
            MockResponse::make(['message' => 'Limit Exceeded'], 429),
        ]);

        try {
            $this->impatientConnector()->send(new StubRequest, $mockClient);
            self::fail('An exhausted quota should surface as a RateLimitException.');
        } catch (RateLimitException) {
            // Expected. The assertion that matters is the attempt count below.
        }

        self::assertCount(3, $mockClient->getRecordedResponses());
    }

    #[Test]
    public function a_rate_limit_that_clears_on_a_retry_never_surfaces(): void
    {
        $mockClient = new MockClient([
            MockResponse::make(['message' => 'Limit Exceeded'], 429),
            MockResponse::make(['players' => []]),
        ]);

        $response = $this->impatientConnector()->send(new StubRequest, $mockClient);

        self::assertTrue($response->successful());
        self::assertCount(2, $mockClient->getRecordedResponses());
    }

    #[Test]
    public function an_authentication_failure_is_not_retried(): void
    {
        $mockClient = new MockClient([MockResponse::make(['message' => 'Forbidden'], 403)]);

        try {
            new FantasyProsConnector('secret-key')->send(new StubRequest, $mockClient);
            self::fail('A rejected key should surface as an AuthenticationException.');
        } catch (AuthenticationException) {
            // Expected.
        }

        // A second attempt would have drained a queue holding only one response.
        self::assertCount(1, $mockClient->getRecordedResponses());
    }

    /**
     * AC #4. A limited tier answers 200 and quietly returns fewer players than
     * it counts. That is not a failure and must not be mapped to one.
     */
    #[Test]
    public function a_truncated_response_is_not_an_error(): void
    {
        $connector = new FantasyProsConnector('secret-key');
        $mockClient = new MockClient([
            MockResponse::make([
                'sport' => 'NFL',
                'season' => 2025,
                'week' => 10,
                'count' => 61,
                'players' => [[
                    'player_id' => 16393,
                    'player_name' => 'Christian McCaffrey',
                    'short_name' => 'C. McCaffrey',
                    'first_name' => 'Christian',
                    'last_name' => 'McCaffrey',
                    'reverse_name' => 'McCaffrey, Christian',
                    'position_id' => 'RB',
                    'team_id' => 'SF',
                ]],
                'public_api_limited' => true,
                'tier' => 'free',
                'limit' => 10,
            ]),
        ]);
        $connector->withMockClient($mockClient);

        $players = $connector->players(Sport::Nfl);

        self::assertTrue($players->truncated());
        self::assertTrue($players->limits->limited);
        self::assertSame('free', $players->limits->tier);
        self::assertSame(10, $players->limits->limit);
        self::assertCount(1, $players->players);
        self::assertSame(61, $players->count);

        // Asked for nothing beyond the sport, so nothing beyond the sport was
        // sent. The endpoint method must not smuggle in defaults of its own.
        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }

    /**
     * Send a stub request against queued failures and return what came out.
     */
    private function failureFrom(MockResponse ...$responses): RequestException
    {
        $mockClient = new MockClient(array_values($responses));

        try {
            $this->impatientConnector()->send(new StubRequest, $mockClient);
        } catch (RequestException $requestException) {
            return $requestException;
        }

        self::fail('The request was expected to fail.');
    }

    /**
     * A connector that retries as configured but without waiting between goes.
     *
     * The real 500ms interval and exponential backoff are asserted in
     * `FantasyProsConnectorTest`; sleeping through them here would only buy the
     * same coverage at several seconds a test, and it made Infection fall back
     * to killing mutants by timeout rather than by assertion.
     */
    private function impatientConnector(): FantasyProsConnector
    {
        $connector = new FantasyProsConnector('secret-key');
        $connector->retryInterval = 0;
        $connector->useExponentialBackoff = false;

        return $connector;
    }
}

<?php

declare(strict_types=1);

namespace FantasyPros\Tests;

use FantasyPros\Exceptions\MissingApiKeyException;
use FantasyPros\FantasyProsConnector;
use FantasyPros\Tests\Doubles\StubRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(FantasyProsConnector::class)]
#[CoversClass(MissingApiKeyException::class)]
final class FantasyProsConnectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'tab' => ["\t"];
        yield 'newline' => ["\n"];
    }

    #[Test]
    public function it_authenticates_with_the_api_key_header(): void
    {
        $mockClient = new MockClient([MockResponse::make(['players' => []])]);

        new FantasyProsConnector('secret-key')->send(new StubRequest, $mockClient);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame(
            'secret-key',
            $pendingRequest->headers()->get(FantasyProsConnector::API_KEY_HEADER),
        );
    }

    #[Test]
    public function it_asks_for_json(): void
    {
        $mockClient = new MockClient([MockResponse::make(['players' => []])]);

        new FantasyProsConnector('secret-key')->send(new StubRequest, $mockClient);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame('application/json', $pendingRequest->headers()->get('Accept'));
    }

    #[Test]
    public function it_targets_the_documented_base_url(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json',
            new FantasyProsConnector('secret-key')->resolveBaseUrl(),
        );
    }

    #[Test]
    #[DataProvider('unusableKeys')]
    public function it_refuses_a_blank_api_key(string $apiKey): void
    {
        $this->expectException(MissingApiKeyException::class);
        $this->expectExceptionMessage('The FantasyPros API key cannot be empty.');

        new FantasyProsConnector($apiKey);
    }

    #[Test]
    public function it_builds_itself_from_the_environment(): void
    {
        $this->setVar(FantasyProsConnector::API_KEY_ENV_VAR, 'key-from-env');

        self::assertSame('key-from-env', $this->apiKeyOf(FantasyProsConnector::fromEnvironment()));
    }

    #[Test]
    public function a_missing_environment_key_fails_before_any_request_is_made(): void
    {
        $this->forgetVar(FantasyProsConnector::API_KEY_ENV_VAR);

        $this->expectException(MissingApiKeyException::class);
        $this->expectExceptionMessage(
            'No FantasyPros API key found. Set FANTASYPROS_API_KEY in your environment or '
            .'.env file; request a key at https://secure.fantasypros.com/api-keys/request/.'
        );

        FantasyProsConnector::fromEnvironment();
    }

    #[Test]
    public function a_whitespace_only_environment_key_is_treated_as_absent(): void
    {
        $this->setVar(FantasyProsConnector::API_KEY_ENV_VAR, '   ');

        $this->expectException(MissingApiKeyException::class);

        FantasyProsConnector::fromEnvironment();
    }

    /**
     * $_SERVER wins over $_ENV, which wins over the process environment.
     *
     * Clears all three first: in record mode .env has already populated
     * $_SERVER and $_ENV with the real key, which would both mask the value
     * under test and print the secret into the failure diff.
     */
    #[Test]
    public function the_environment_key_is_resolved_in_precedence_order(): void
    {
        $this->forgetVar(FantasyProsConnector::API_KEY_ENV_VAR);

        putenv(FantasyProsConnector::API_KEY_ENV_VAR.'=from-getenv');

        self::assertSame('from-getenv', $this->apiKeyOf(FantasyProsConnector::fromEnvironment()));

        $_ENV[FantasyProsConnector::API_KEY_ENV_VAR] = 'from-env';

        self::assertSame('from-env', $this->apiKeyOf(FantasyProsConnector::fromEnvironment()));

        $_SERVER[FantasyProsConnector::API_KEY_ENV_VAR] = 'from-server';

        self::assertSame('from-server', $this->apiKeyOf(FantasyProsConnector::fromEnvironment()));
    }

    #[Test]
    public function it_uses_the_documented_timeouts(): void
    {
        $connector = new FantasyProsConnector('secret-key');

        self::assertSame(10.0, $connector->getConnectTimeout());
        self::assertSame(30.0, $connector->getRequestTimeout());
    }

    #[Test]
    public function it_allows_three_attempts_with_exponential_backoff(): void
    {
        $connector = new FantasyProsConnector('secret-key');

        self::assertSame(3, $connector->tries);
        self::assertSame(500, $connector->retryInterval);
        self::assertTrue($connector->useExponentialBackoff);
    }

    #[Test]
    public function it_retries_rate_limiting(): void
    {
        $mockClient = new MockClient([
            MockResponse::make(['error' => 'slow down'], 429),
            MockResponse::make(['players' => []]),
        ]);

        $response = new FantasyProsConnector('secret-key')->send(new StubRequest, $mockClient);

        self::assertTrue($response->successful());
        self::assertCount(2, $mockClient->getRecordedResponses());
    }

    #[Test]
    public function it_retries_a_server_fault(): void
    {
        $mockClient = new MockClient([
            MockResponse::make(['error' => 'upstream exploded'], 500),
            MockResponse::make(['players' => []]),
        ]);

        $response = new FantasyProsConnector('secret-key')->send(new StubRequest, $mockClient);

        self::assertTrue($response->successful());
        self::assertCount(2, $mockClient->getRecordedResponses());
    }

    #[Test]
    public function it_does_not_retry_a_rejected_key(): void
    {
        $mockClient = new MockClient([MockResponse::make(['error' => 'bad key'], 401)]);

        try {
            new FantasyProsConnector('secret-key')->send(new StubRequest, $mockClient);
            self::fail('A 401 should surface as a RequestException.');
        } catch (RequestException $requestException) {
            self::assertSame(401, $requestException->getResponse()->status());
        }

        // A second attempt would have drained the single queued response.
        self::assertCount(1, $mockClient->getRecordedResponses());
    }

    /**
     * Read back the key a connector authenticates with, via the header it sets.
     */
    private function apiKeyOf(FantasyProsConnector $connector): string
    {
        $mockClient = new MockClient([MockResponse::make(['players' => []])]);

        $connector->send(new StubRequest, $mockClient);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);

        $key = $pendingRequest->headers()->get(FantasyProsConnector::API_KEY_HEADER);

        self::assertIsString($key);

        return $key;
    }
}

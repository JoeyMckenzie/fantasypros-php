<?php

declare(strict_types=1);

namespace Fantasy\Tests\Sdk;

use Fantasy\Sdk\FantasyProsConnector;
use Fantasy\Tests\FixtureMode;
use Fantasy\Tests\TestCase;
use Psr\Http\Message\UriInterface;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

/**
 * Shared plumbing for endpoint tests.
 *
 * The connector is built from the current fixture mode, so offline runs use a
 * stand-in key and recording runs use the real one.
 */
abstract class RequestTestCase extends TestCase
{
    /**
     * The full URL a request resolves to, without its query string.
     */
    final protected function uriFor(Request $request): string
    {
        return (string) $this->uri($request)->withQuery('');
    }

    /**
     * The query string a request builds, as it goes on the wire.
     */
    final protected function queryFor(Request $request): string
    {
        return $this->uri($request)->getQuery();
    }

    /**
     * The query parameters a request registers, before they reach the URL.
     *
     * Distinct from queryFor(): http_build_query drops nulls on its way to the
     * URI, so a null that was never filtered out is invisible there but visible
     * here.
     *
     * @return array<array-key, mixed>
     */
    final protected function queryParametersFor(Request $request): array
    {
        return $this->connector()->createPendingRequest($request)->query()->all();
    }

    /**
     * Send a request against a recorded fixture and hydrate its DTO.
     *
     * Offline this replays the fixture; in record mode a missing fixture is
     * fetched from the live API and written to disk.
     */
    final protected function dtoFrom(Request $request, string $fixture): mixed
    {
        $mockClient = new MockClient([
            $request::class => MockResponse::fixture($fixture),
        ]);

        return $this->connector()->send($request, $mockClient)->dtoOrFail();
    }

    final protected function connector(): FantasyProsConnector
    {
        return new FantasyProsConnector(FixtureMode::current()->apiKey());
    }

    /**
     * getUri() rather than getUrl() -- only the former folds in the query.
     */
    private function uri(Request $request): UriInterface
    {
        return $this->connector()->createPendingRequest($request)->getUri();
    }
}

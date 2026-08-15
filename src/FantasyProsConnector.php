<?php

declare(strict_types=1);

namespace FantasyPros;

use FantasyPros\Concerns\SupportsComparisonEndpoints;
use FantasyPros\Concerns\SupportsExpertEndpoints;
use FantasyPros\Concerns\SupportsInjuryEndpoints;
use FantasyPros\Concerns\SupportsNewsEndpoints;
use FantasyPros\Concerns\SupportsPlayerEndpoints;
use FantasyPros\Concerns\SupportsProjectionEndpoints;
use FantasyPros\Concerns\SupportsRankingEndpoints;
use FantasyPros\Exceptions\MissingApiKeyException;
use Override;
use Saloon\Contracts\Authenticator;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;

final class FantasyProsConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use HasTimeout;
    use SupportsComparisonEndpoints;
    use SupportsExpertEndpoints;
    use SupportsInjuryEndpoints;
    use SupportsNewsEndpoints;
    use SupportsPlayerEndpoints;
    use SupportsProjectionEndpoints;
    use SupportsRankingEndpoints;

    public const string BASE_URL = 'https://api.fantasypros.com/public/v2/json';

    public const string API_KEY_HEADER = 'x-api-key';

    public const string API_KEY_ENV_VAR = 'FANTASYPROS_API_KEY';

    public function __construct(private readonly string $apiKey)
    {
        if (mb_trim($this->apiKey) === '') {
            throw MissingApiKeyException::blank();
        }

        $this->tries = 3;
        $this->retryInterval = 500;
        $this->useExponentialBackoff = true;
    }

    /**
     * Build a connector from the environment, reading FANTASYPROS_API_KEY.
     */
    public static function fromEnvironment(): self
    {
        $apiKey = $_SERVER[self::API_KEY_ENV_VAR] ?? $_ENV[self::API_KEY_ENV_VAR] ?? getenv(self::API_KEY_ENV_VAR);

        if (! is_string($apiKey) || mb_trim($apiKey) === '') {
            throw MissingApiKeyException::absentFromEnvironment(self::API_KEY_ENV_VAR);
        }

        return new self($apiKey);
    }

    #[Override]
    public function resolveBaseUrl(): string
    {
        return self::BASE_URL;
    }

    /**
     * Overrides HasTimeout's default. Not #[Override] -- the trait is used by
     * this class, so there is no inherited method being replaced.
     */
    public function getConnectTimeout(): float
    {
        return 10;
    }

    public function getRequestTimeout(): float
    {
        return 30;
    }

    /**
     * Decide whether a failed attempt is worth repeating.
     */
    #[Override]
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        // Never got a response at all -- DNS, TLS, timeout. Worth another go.
        if ($exception instanceof FatalRequestException) {
            return true;
        }

        $status = $exception->getResponse()->status();

        // Only rate limiting and server faults are transient. A 401 (bad key)
        // or 400 (bad parameter) fails identically however often we ask.
        return $status === 429 || $status >= 500;
    }

    #[Override]
    protected function defaultAuth(): Authenticator
    {
        return new HeaderAuthenticator($this->apiKey, self::API_KEY_HEADER);
    }
}

<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use Saloon\Http\Response;
use Throwable;

/**
 * Maps a failed response onto the exception type that fits it.
 *
 * Lives here rather than on the connector so the connector stays pure Saloon
 * plumbing (auth, timeouts, retry), with the body reading kept next to the
 * types it produces.
 *
 * The body goes through plain `json_decode` rather than `Payload`, because
 * `Payload` throws `UnexpectedPayloadException` from this namespace. Reading
 * through it would point an arrow from `exceptions` back into
 * `data.infrastructure` and close a cycle Deptrac rejects. Same reason
 * `ApiLimits` sits in `Data/Api`; see FANTASY-15.
 */
final class RequestFailure
{
    /**
     * Only 403 and 429 actually show up. A wrong key, an empty key and no key
     * header at all all answer `403 {"message":"Forbidden"}`. 401 never comes
     * back, and neither does the 400 the spec documents, since bad parameters
     * get silently ignored and answered with a 200. Both are still mapped in
     * case a gateway change starts returning them, but neither is reachable
     * from the API as it stands.
     */
    public static function toException(Response $response, ?Throwable $previous = null): Throwable
    {
        $body = self::decode($response);
        $status = $response->status();
        $message = self::messageFor($status, self::readString($body, 'message'));

        return match (true) {
            $status === 400 => new ValidationException(
                $response,
                $message,
                previous: $previous,
                parameter: self::readString($body, 'parameter'),
                validFormat: self::readString($body, 'valid_format'),
            ),
            $status === 401, $status === 403 => new AuthenticationException($response, $message, previous: $previous),
            $status === 429 => new RateLimitException($response, $message, previous: $previous),
            default => new ApiRequestException($response, $message, previous: $previous),
        };
    }

    /**
     * Null when the API sent no explanation, which lets `RequestException`
     * build its own message from the status and body instead of us inventing a
     * worse one.
     */
    private static function messageFor(int $status, ?string $apiMessage): ?string
    {
        if ($apiMessage === null) {
            return null;
        }

        return sprintf('FantasyPros returned %d: %s', $status, $apiMessage);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(Response $response): array
    {
        $decoded = json_decode($response->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private static function readString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}

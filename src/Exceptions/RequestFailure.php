<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use Saloon\Http\Response;
use Throwable;

/**
 * Maps a failed response onto the exception type that fits it.
 *
 * Lives here rather than on the connector so the connector stays the pure
 * Saloon plumbing it was trimmed back to -- auth, timeouts, retry -- with the
 * body reading kept next to the types it produces.
 *
 * The body is read with plain `json_decode` rather than through `Payload`. That
 * is deliberate: `Payload` throws `UnexpectedPayloadException` from this very
 * namespace, so reading through it would point an arrow from `exceptions` back
 * into `data.infrastructure` and complete a cycle Deptrac would reject. The
 * same reasoning put `ApiLimits` in `Data/Api` -- see FANTASY-15.
 */
final class RequestFailure
{
    /**
     * Only 403 and 429 have been observed from the live API. A wrong key, an
     * empty key and no key header at all all answer `403 {"message":"Forbidden"}`
     * -- 401 is never returned, and neither is the 400 the spec documents, since
     * invalid parameters are silently ignored and answered with a 200. Both are
     * still mapped, because the spec documents them and a gateway change could
     * start returning them, but neither is reachable from the API as it stands.
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
     * Null when the API sent no explanation, so `RequestException` composes its
     * own message from the status and body rather than this inventing a worse
     * one.
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

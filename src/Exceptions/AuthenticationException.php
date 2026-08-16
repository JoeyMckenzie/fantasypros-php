<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * The API refused the key.
 *
 * In practice always a 403 carrying `{"message":"Forbidden"}`: a wrong key, an
 * empty key and a missing key header are indistinguishable from the outside.
 * 401 is mapped here too, though the live API has never been seen to send one.
 *
 * Not retried -- a rejected key fails identically however often it is asked.
 */
final class AuthenticationException extends RequestException implements FantasyProsRequestException {}

<?php

declare(strict_types=1);

namespace FantasyPros\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * The API refused the key.
 *
 * In practice this is always a 403 carrying `{"message":"Forbidden"}`. A wrong
 * key, an empty key and a missing key header all look the same from outside.
 * 401 is mapped here too, though the API has never been seen to send one.
 *
 * Never retried, since a rejected key fails the same however often you ask.
 */
final class AuthenticationException extends RequestException implements FantasyProsRequestException {}

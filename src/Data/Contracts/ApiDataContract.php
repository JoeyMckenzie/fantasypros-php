<?php

declare(strict_types=1);

namespace FantasyPros\Data\Contracts;

use FantasyPros\Data\Infrastructure\Payload;

/**
 * A DTO hydrated from one decoded JSON object.
 *
 * Everything under Data that is built from part of a response reads through
 * `Payload` rather than touching `mixed` itself, so this pins that convention
 * for the DTOs still to come as the remaining endpoints land.
 *
 * The envelope DTOs (the ones a request hydrates directly, like
 * `PlayerCollection` and `InjuryReport`) take a whole `Response` instead, so
 * they're not part of this contract.
 */
interface ApiDataContract
{
    public static function fromPayload(Payload $payload): self;
}

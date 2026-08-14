<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Data;

/**
 * A DTO hydrated from one decoded JSON object.
 *
 * Everything under Data that is built from part of a response reads through
 * `Payload` rather than touching `mixed` itself, so this pins that convention
 * for the DTOs still to come as the remaining endpoints land.
 *
 * The envelope DTOs -- the ones a request hydrates directly, such as
 * `PlayerCollection` and `InjuryReport` -- take a whole `Response` instead and
 * so are not part of this contract.
 */
interface ApiDataContract
{
    public static function fromPayload(Payload $payload): self;
}

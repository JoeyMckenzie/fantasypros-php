<?php

declare(strict_types=1);

namespace FantasyPros\Data\Api;

use FantasyPros\Data\Contracts\ApiDataContract;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflScoringType;

/**
 * One player from the projections endpoint.
 *
 * The projected stats are a map rather than typed per-position properties.
 * Three reasons:
 *
 * - The spec's per-position schemas are wrong. It declares DST projections
 *   carrying `def_pa_a` through `def_pa_g`; the live route returns `def_pa` and
 *   `def_tyda` instead, and none of the seven lettered keys exist. Four rigid
 *   DTOs built from those schemas would throw on real responses.
 * - `2pt_tds` isn't a legal property name. It leads with a digit, so you could
 *   only ever reach it as `$player->{'2pt_tds'}`.
 * - Typed variants would put an `instanceof` back at every call site, which is
 *   what moving the endpoints onto the connector got rid of.
 *
 * `RankedPlayer::$ranks` already models a heterogeneous stat bag the same way.
 */
final readonly class ProjectedPlayer implements ApiDataContract
{
    /**
     * @param  array<array-key, float>  $stats  the projected stat line, keyed as the
     *                                          API keys it. The key set varies by position: a QB and a DST share
     *                                          only the three points keys, so read it through `stat()` rather than
     *                                          assuming a key is present.
     */
    public function __construct(
        public int $id,
        public ?int $mflId,
        public string $name,
        public string $positionId,
        public string $teamId,
        public ?string $profileUrl,
        public array $stats,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            id: $payload->int('fpid'),
            mflId: $payload->nullableInt('mflid'),
            name: $payload->string('name'),
            positionId: $payload->string('position_id'),
            teamId: $payload->string('team_id'),
            profileUrl: $payload->nullableString('filename'),
            stats: $payload->has('stats') ? $payload->floatMap('stats') : [],
        );
    }

    /**
     * The projected fantasy points for a scoring format.
     *
     * Every position carries all three keys here, which the ranking routes
     * don't do: there a QB's PPR and HALF ranks come back empty, since scoring
     * only changes what a pass-catcher is worth. For a QB or a DST all three
     * hold the same number as `points`.
     */
    public function points(NflScoringType $scoring = NflScoringType::Standard): ?float
    {
        return $this->stat(match ($scoring) {
            NflScoringType::Standard => 'points',
            NflScoringType::Ppr => 'points_ppr',
            NflScoringType::Half => 'points_half',
        });
    }

    /**
     * One projected stat, or null when this position does not carry that key.
     */
    public function stat(string $key): ?float
    {
        return $this->stats[$key] ?? null;
    }

    public function position(): ?NflPosition
    {
        return NflPosition::tryFrom($this->positionId);
    }
}

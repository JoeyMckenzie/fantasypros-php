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
 * The projected stats are a map rather than typed per-position properties, and
 * that is a deliberate reading of the payload rather than laziness:
 *
 * - **The spec's per-position schemas are wrong.** It declares DST projections
 *   carrying `def_pa_a` through `def_pa_g`; the live route returns `def_pa` and
 *   `def_tyda` instead, and none of the seven lettered keys exist. Generating
 *   four rigid DTOs from those schemas produces classes that throw on genuine
 *   responses.
 * - **One stat key is not a legal property name.** `2pt_tds` leads with a digit,
 *   so it could only ever be reached as `$player->{'2pt_tds'}`.
 * - **Typed variants would force callers to narrow.** A union of four stat DTOs
 *   puts an `instanceof` back at every call site, which is exactly what moving
 *   the endpoints onto the connector set out to remove.
 *
 * `RankedPlayer::$ranks` already models a heterogeneous stat bag the same way.
 */
final readonly class ProjectedPlayer implements ApiDataContract
{
    /**
     * @param  array<array-key, float>  $stats  the projected stat line, keyed as the
     *                                          API keys it. The key set varies by position -- passing stats for a
     *                                          QB and a DST share only the three points keys -- so read it through
     *                                          `stat()` rather than assuming a key is present.
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
     * Unlike the ranking routes -- where a QB's PPR and HALF ranks come back
     * empty because scoring only changes what a pass-catcher is worth -- every
     * position carries all three keys here. For a QB or a DST they simply hold
     * the same number as `points`.
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

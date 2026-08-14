<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Data;

use Fantasy\Sdk\Enums\NflPosition;

/**
 * One NFL player from the players endpoint.
 *
 * Only identity is treated as guaranteed. Every rank is nullable because the
 * `ecr=excluded` variant returns players with no consensus ranking at all, and
 * `rank_ecr_pos` is only populated when `show=pos_rank` is requested.
 */
final readonly class NflPlayer implements ApiDataContract
{
    /**
     * @param  list<string>  $positions
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $shortName,
        public string $firstName,
        public string $lastName,
        public string $reverseName,
        public string $positionId,
        public array $positions,
        public string $teamId,
        public ?string $profileUrl,
        public ?string $sportsdataPlayerId,
        public ?int $ecrRank,
        public ?int $adpRank,
        public ?int $positionalEcrRank,
        public ?int $pprEcrRank,
        public ?int $halfPprEcrRank,
        public ?int $pprAdpRank,
        public ?string $birthdate,
        public ?int $age,
        public ?int $draftClass,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            id: $payload->int('player_id'),
            name: $payload->string('player_name'),
            shortName: $payload->string('short_name'),
            firstName: $payload->string('first_name'),
            lastName: $payload->string('last_name'),
            reverseName: $payload->string('reverse_name'),
            positionId: $payload->string('position_id'),
            positions: $payload->strings('positions'),
            teamId: $payload->string('team_id'),
            profileUrl: $payload->nullableString('filename'),
            sportsdataPlayerId: $payload->nullableString('sportsdata_player_id'),
            ecrRank: $payload->nullableInt('rank_ecr'),
            adpRank: $payload->nullableInt('rank_adp'),
            positionalEcrRank: $payload->nullableInt('rank_ecr_pos'),
            pprEcrRank: $payload->nullableInt('rank_ecr_ppr'),
            halfPprEcrRank: $payload->nullableInt('rank_ecr_half'),
            pprAdpRank: $payload->nullableInt('rank_adp_ppr'),
            birthdate: $payload->nullableString('birthdate'),
            age: $payload->nullableInt('age'),
            draftClass: $payload->nullableInt('draft_class'),
        );
    }

    /**
     * The primary position as an enum, or null if the API reports one the spec
     * does not enumerate. Deliberately non-throwing -- an unknown position
     * should not break an otherwise usable player.
     */
    public function position(): ?NflPosition
    {
        return NflPosition::tryFrom($this->positionId);
    }
}

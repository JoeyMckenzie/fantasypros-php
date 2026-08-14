<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Data;

/**
 * The thin player description the comparison endpoint returns in its `players`
 * lookup. Much smaller than NflPlayer -- no ranks, no IDs beyond the map key.
 */
final readonly class ComparedPlayer implements ApiDataContract
{
    public function __construct(
        public string $name,
        public string $teamId,
        public string $positionId,
        public ?string $profileUrl,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            name: $payload->string('player_name'),
            teamId: $payload->string('player_team_id'),
            positionId: $payload->string('player_position_id'),
            profileUrl: $payload->nullableString('player_page_url'),
        );
    }
}

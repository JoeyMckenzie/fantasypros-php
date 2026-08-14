<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Data;

/**
 * One expert's rank for one player.
 */
final readonly class ExpertRank implements ApiDataContract
{
    public const string CONSENSUS_EXPERT_ID = '_0';

    /**
     * @param  string  $expertId  a string, not an int: the consensus row uses the
     *                            sentinel `_0` alongside real numeric expert IDs
     */
    public function __construct(
        public string $expertId,
        public int $rank,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            expertId: $payload->string('expert_id'),
            rank: $payload->int('rank'),
        );
    }

    /**
     * Whether this is FantasyPros' own consensus rank rather than a person's.
     */
    public function isConsensus(): bool
    {
        return $this->expertId === self::CONSENSUS_EXPERT_ID;
    }
}

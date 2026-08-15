<?php

declare(strict_types=1);

namespace FantasyPros\Data\Api;

use FantasyPros\Data\Contracts\ApiDataContract;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\RankMetric;

/**
 * One player from the rankings endpoint.
 *
 * Distinct from `ConsensusRankedPlayer`: this endpoint returns every ranking a
 * player holds, nested metric -> scoring -> position, whereas consensus-rankings
 * returns one flat ranking for the position that was asked for.
 *
 * Every field but the identity block is optional, because `min=true` reduces the
 * response to just `id` and `rank`.
 */
final readonly class RankedPlayer implements ApiDataContract
{
    /**
     * @param  list<string>  $positions
     * @param  array<string, array<string, array<array-key, float>>>  $ranks  metric ->
     *                                                                        scoring -> position -> rank. Read it through `rank()` rather than
     *                                                                        directly: the scoring level carries undocumented keys beyond
     *                                                                        STD/PPR/HALF, including `ROS-STD` and `DYN`.
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $shortName,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $reverseName,
        public ?string $positionId,
        public array $positions,
        public ?string $teamId,
        public ?string $profileUrl,
        public array $ranks,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            id: $payload->int('id'),
            name: $payload->nullableString('player_name'),
            shortName: $payload->nullableString('short_name'),
            firstName: $payload->nullableString('first_name'),
            lastName: $payload->nullableString('last_name'),
            reverseName: $payload->nullableString('reverse_name'),
            positionId: $payload->nullableString('position_id'),
            positions: $payload->strings('positions'),
            teamId: $payload->nullableString('team_id'),
            profileUrl: $payload->nullableString('filename'),
            ranks: self::readRanks($payload),
        );
    }

    /**
     * One ranking, or null when the request did not ask for that metric or the
     * player holds no rank for that scoring and position combination.
     *
     * Returns a float because the API is not consistent even within a block:
     * `ECR_AVG` carries plain ints alongside decimals.
     */
    public function rank(RankMetric $metric, string $scoring, string $position): ?float
    {
        return $this->ranks[$metric->value][$scoring][$position] ?? null;
    }

    /**
     * Convenience for the common case, where the position is the player's own.
     */
    public function consensusRank(string $scoring): ?float
    {
        if ($this->positionId === null) {
            return null;
        }

        return $this->rank(RankMetric::Consensus, $scoring, $this->positionId);
    }

    public function position(): ?NflPosition
    {
        return $this->positionId === null ? null : NflPosition::tryFrom($this->positionId);
    }

    /**
     * @return array<string, array<string, array<array-key, float>>>
     */
    private static function readRanks(Payload $payload): array
    {
        if (! $payload->has('rank')) {
            return [];
        }

        $ranks = [];

        foreach ($payload->objectMap('rank') as $metric => $scorings) {
            $byScoring = [];

            foreach ($scorings->keys() as $scoring) {
                $byScoring[$scoring] = $scorings->floatMap($scoring);
            }

            $ranks[(string) $metric] = $byScoring;
        }

        return $ranks;
    }
}

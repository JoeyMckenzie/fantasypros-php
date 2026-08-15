<?php

declare(strict_types=1);

namespace FantasyPros\Data\Api;

use FantasyPros\Data\Contracts\ApiDataContract;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\NflPosition;

/**
 * One player's scored points from the player-points endpoint.
 *
 * Almost nothing here is guaranteed, in two different directions:
 *
 * - `min=true` strips the name, position and team, leaving the ID and numbers.
 * - A player who did not appear in the requested week range comes back as an
 *   identity block alone -- no `games`, `points`, `average` or `weeks` -- even
 *   though the spec marks all four required. Roughly a third of a recorded
 *   quarterback set is in that state. Those players read as a scoreless line
 *   rather than throwing, since not playing is a real answer and zero is the
 *   truthful count.
 */
final readonly class PlayerPoints implements ApiDataContract
{
    /**
     * @param  array<array-key, float>  $weeks  points scored, keyed by week number
     *                                          in the order the API returned them. Only weeks the player actually
     *                                          played appear, so this is sparser than the requested start-to-end
     *                                          range -- reach for `inWeek()` rather than indexing blind.
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $positionId,
        public ?string $teamId,
        public ?string $profileUrl,
        public int $games,
        public float $points,
        public float $average,
        public array $weeks,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            id: $payload->int('player_id'),
            name: $payload->nullableString('player_name'),
            positionId: $payload->nullableString('position_id'),
            teamId: $payload->nullableString('team_id'),
            profileUrl: $payload->nullableString('filename'),
            games: $payload->nullableInt('games') ?? 0,
            points: $payload->nullableFloat('points') ?? 0.0,
            average: $payload->nullableFloat('average') ?? 0.0,
            weeks: $payload->has('weeks') ? $payload->floatMap('weeks') : [],
        );
    }

    /**
     * Points scored in one week, or null when the player did not play it.
     */
    public function inWeek(int $week): ?float
    {
        return $this->weeks[$week] ?? null;
    }

    /**
     * The player's best single week, or null when they have not played.
     */
    public function bestWeek(): ?float
    {
        if ($this->weeks === []) {
            return null;
        }

        return max($this->weeks);
    }

    public function position(): ?NflPosition
    {
        return $this->positionId === null ? null : NflPosition::tryFrom($this->positionId);
    }
}

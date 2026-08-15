<?php

declare(strict_types=1);

namespace FantasyPros\Data\Envelopes;

use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\ComparedExpert;
use FantasyPros\Data\Api\ComparedPlayer;
use FantasyPros\Data\Api\ExpertRank;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use Saloon\Http\Response;

/**
 * The compare-players envelope.
 *
 * NFL-shaped on purpose: for NFL the rankings nest scoring format over player
 * ID, whereas MLB/NBA/NHL drop the scoring level and key straight by player.
 * A second sport wants its own DTO rather than a nullable middle layer here.
 */
final readonly class PlayerComparison
{
    /**
     * Player and expert IDs are `array-key`, not `string`: PHP casts a numeric
     * string key such as "17240" to an int, so these maps are int-keyed for
     * players and mixed-keyed for experts (the consensus row uses `_0`).
     *
     * @param  array<string, array<int, list<ExpertRank>>>  $rankings  scoring format => player ID => ranks
     * @param  array<array-key, ComparedPlayer>  $players  keyed by player ID
     * @param  array<array-key, ComparedExpert>  $experts  keyed by expert ID
     */
    public function __construct(
        public Sport $sport,
        public int $year,
        public int $week,
        public string $positionId,
        public string $rankingType,
        public array $rankings,
        public array $players,
        public array $experts,
        public ApiLimits $limits,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $payload = Payload::fromResponse($response);

        return new self(
            sport: Sport::from($payload->string('sport')),
            year: $payload->int('year'),
            week: $payload->int('week'),
            positionId: $payload->string('position_id'),
            rankingType: $payload->string('ranking_type'),
            rankings: self::readRankings($payload),
            players: array_map(
                ComparedPlayer::fromPayload(...),
                $payload->has('players') ? $payload->objectMap('players') : [],
            ),
            experts: array_map(
                ComparedExpert::fromPayload(...),
                $payload->has('experts') ? $payload->objectMap('experts') : [],
            ),
            limits: ApiLimits::fromPayload($payload),
        );
    }

    /**
     * Every expert rank recorded for one player in one scoring format.
     *
     * @return list<ExpertRank>
     */
    public function ranksFor(NflScoringType $scoringFormat, int $playerId): array
    {
        return $this->rankings[$scoringFormat->value][$playerId] ?? [];
    }

    /**
     * FantasyPros' consensus rank for a player, ignoring individual experts.
     */
    public function consensusRank(NflScoringType $scoringFormat, int $playerId): ?int
    {
        foreach ($this->ranksFor($scoringFormat, $playerId) as $rank) {
            if ($rank->isConsensus()) {
                return $rank->rank;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, list<ExpertRank>>>
     */
    private static function readRankings(Payload $payload): array
    {
        $rankings = [];

        foreach ($payload->objectMap('rankings') as $scoringFormat => $byPlayer) {
            $perPlayer = [];

            foreach ($byPlayer->keys() as $playerId) {
                // Cast explicitly: PHP would coerce the numeric key anyway, and
                // being honest about it keeps the declared type true.
                $perPlayer[(int) $playerId] = array_map(
                    ExpertRank::fromPayload(...),
                    $byPlayer->objects($playerId),
                );
            }

            // Scoring formats are non-numeric, so these keys stay strings.
            $rankings[(string) $scoringFormat] = $perPlayer;
        }

        return $rankings;
    }
}

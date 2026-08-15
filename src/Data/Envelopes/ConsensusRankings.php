<?php

declare(strict_types=1);

namespace FantasyPros\Data\Envelopes;

use FantasyPros\Data\Api\ConsensusRankedPlayer;
use FantasyPros\Data\Api\RankingExpert;
use FantasyPros\Data\Infrastructure\ApiLimits;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\Sport;
use Saloon\Http\Response;

/**
 * The consensus-rankings endpoint envelope.
 */
final readonly class ConsensusRankings
{
    /**
     * @param  string  $label  the display name of the ranking set, e.g. `Weekly PPR`;
     *                         the wire calls this `type`
     * @param  string  $rankingType  the machine name, e.g. `weekly`. Lowercase, despite
     *                               the spec typing it as the uppercase ranking-type vocabulary.
     * @param  ?string  $filters  the expert filter the API actually applied, echoed back
     *                            comma-joined even though it must be sent colon-joined
     * @param  list<ConsensusRankedPlayer>  $players
     * @param  array<array-key, RankingExpert>  $experts  keyed by expert ID; populated only
     *                                                    when the request asked for experts
     */
    public function __construct(
        public Sport $sport,
        public string $label,
        public string $rankingType,
        public int $year,
        public int $week,
        public string $positionId,
        public ?string $scoring,
        public ?string $filters,
        public int $count,
        public int $totalExperts,
        public ?string $lastUpdated,
        public ?int $lastUpdatedTimestamp,
        public array $players,
        public array $experts,
        public ApiLimits $limits,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $payload = Payload::fromResponse($response);

        return new self(
            sport: Sport::from($payload->string('sport')),
            label: $payload->string('type'),
            rankingType: $payload->string('ranking_type_name'),
            year: $payload->int('year'),
            week: $payload->int('week'),
            positionId: $payload->string('position_id'),
            scoring: $payload->nullableString('scoring'),
            filters: $payload->nullableString('filters'),
            count: $payload->int('count'),
            totalExperts: $payload->int('total_experts'),
            lastUpdated: $payload->nullableString('last_updated'),
            lastUpdatedTimestamp: $payload->nullableInt('last_updated_ts'),
            players: array_map(
                ConsensusRankedPlayer::fromPayload(...),
                $payload->objects('players'),
            ),
            experts: RankingExpert::mapFrom($payload),
            limits: ApiLimits::fromPayload($payload),
        );
    }

    /**
     * True when the tier's cap held back players the count says exist.
     */
    public function truncated(): bool
    {
        return count($this->players) < $this->count;
    }
}

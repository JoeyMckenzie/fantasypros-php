<?php

declare(strict_types=1);

namespace FantasyPros\Data\Envelopes;

use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\NflInjury;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\Sport;
use Saloon\Http\Response;

/**
 * The injuries endpoint envelope.
 */
final readonly class InjuryReport
{
    /**
     * @param  list<NflInjury>  $injuries
     */
    public function __construct(
        public Sport $sport,
        public int $count,
        public array $injuries,
        public ApiLimits $limits,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $payload = Payload::fromResponse($response);

        return new self(
            sport: Sport::from($payload->string('sport')),
            count: $payload->int('count'),
            injuries: array_map(
                NflInjury::fromPayload(...),
                $payload->objects('injuries'),
            ),
            limits: ApiLimits::fromPayload($payload),
        );
    }

    /**
     * True when the tier's cap held back injuries the count says exist.
     */
    public function truncated(): bool
    {
        return count($this->injuries) < $this->count;
    }
}

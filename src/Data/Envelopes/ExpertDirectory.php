<?php

declare(strict_types=1);

namespace FantasyPros\Data\Envelopes;

use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\ExpertProfile;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\Sport;
use Saloon\Http\Response;

/**
 * The experts endpoint envelope.
 */
final readonly class ExpertDirectory
{
    /**
     * @param  list<ExpertProfile>  $experts
     */
    public function __construct(
        public Sport $sport,
        public int $count,
        public int $season,
        public int $week,
        public ?int $draftAccuracySeason,
        public ?int $weeklyAccuracySeason,
        public ?int $lastWeeklyAccuracySeason,
        public array $experts,
        public ApiLimits $limits,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $payload = Payload::fromResponse($response);

        return new self(
            sport: Sport::from($payload->string('sport')),
            count: $payload->int('count'),
            season: $payload->int('season'),
            week: $payload->int('week'),
            draftAccuracySeason: $payload->nullableInt('accuracy_draft_season'),
            weeklyAccuracySeason: $payload->nullableInt('accuracy_weekly_season'),
            // Undocumented, alongside the per-expert map of the same name.
            lastWeeklyAccuracySeason: $payload->nullableInt('accuracy_weekly_last_season'),
            experts: array_map(
                ExpertProfile::fromPayload(...),
                $payload->objects('experts'),
            ),
            limits: ApiLimits::fromPayload($payload),
        );
    }

    /**
     * True when the tier's cap held back experts the count says exist.
     */
    public function truncated(): bool
    {
        return count($this->experts) < $this->count;
    }
}

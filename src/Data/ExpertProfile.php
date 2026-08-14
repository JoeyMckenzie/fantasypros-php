<?php

declare(strict_types=1);

namespace FantasyPros\Data;

/**
 * One expert from the experts directory.
 *
 * Distinct from `RankingExpert` (the thin record folded out of a consensus
 * ranking envelope) and from `ComparedExpert` (the compare-players endpoint's
 * own shape).
 */
final readonly class ExpertProfile implements ApiDataContract
{
    /**
     * @param  array<array-key, string>  $positions  position to when that position's
     *                                               rankings were last updated
     * @param  array<array-key, bool>  $defaults  position to whether this expert is a
     *                                            default for it. The wire key is
     *                                            `default`, singular, though the spec
     *                                            documents `defaults`.
     * @param  array<array-key, int>  $draftAccuracy  position to accuracy rank; `ALL` is
     *                                                the overall row. Undocumented, as are
     *                                                the two weekly maps.
     * @param  array<array-key, int>  $weeklyAccuracy
     * @param  array<array-key, int>  $lastSeasonWeeklyAccuracy
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $source,
        public ?string $url,
        public ?string $twitter,
        public ?string $bio,
        public array $positions,
        public array $defaults,
        public array $draftAccuracy,
        public array $weeklyAccuracy,
        public array $lastSeasonWeeklyAccuracy,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            id: $payload->int('expert_id'),
            name: $payload->string('name'),
            source: $payload->string('source'),
            url: $payload->nullableString('url'),
            twitter: $payload->nullableString('twitter'),
            bio: $payload->nullableString('bio'),
            positions: $payload->stringMap('positions'),
            defaults: $payload->boolMap('default'),
            draftAccuracy: $payload->intMap('accuracy_draft'),
            weeklyAccuracy: $payload->intMap('accuracy_weekly'),
            lastSeasonWeeklyAccuracy: $payload->intMap('accuracy_weekly_last_season'),
        );
    }

    /**
     * Whether this expert is a default source for the given position.
     */
    public function isDefaultFor(string $position): bool
    {
        return $this->defaults[$position] ?? false;
    }
}

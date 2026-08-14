<?php

declare(strict_types=1);

namespace FantasyPros\Data;

/**
 * An expert from the comparison endpoint's `experts` lookup.
 */
final readonly class ComparedExpert implements ApiDataContract
{
    /**
     * @param  list<string>  $scoringFormats  the scoring formats this expert
     *                                        publishes ranks for, e.g. STD, PPR, HALF
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public ?string $twitterUrl,
        public ?string $sourceId,
        public ?string $sourceName,
        public ?string $sourceUrl,
        public array $scoringFormats,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            name: $payload->string('expert_name'),
            displayName: $payload->string('expert_display_name'),
            twitterUrl: $payload->nullableString('expert_twitter_url'),
            sourceId: $payload->nullableString('expert_source_id'),
            sourceName: $payload->nullableString('expert_source_name'),
            sourceUrl: $payload->nullableString('expert_source_url'),
            scoringFormats: $payload->strings('ranks'),
        );
    }
}

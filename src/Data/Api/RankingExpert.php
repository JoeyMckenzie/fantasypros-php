<?php

declare(strict_types=1);

namespace FantasyPros\Data\Api;

use FantasyPros\Data\Infrastructure\Payload;

/**
 * One expert behind a consensus ranking set.
 *
 * The wire keeps this split across three parallel ID-keyed maps -- `expert_pub`,
 * `expert_names` and `expert_twitter` -- so this folds them into one object per
 * expert rather than making every caller zip three arrays together.
 *
 * Not an `ApiDataContract`: it is assembled from several sibling maps rather
 * than hydrated from a single JSON object, so it has no `fromPayload`.
 *
 * Distinct from `ComparedExpert` (the compare-players endpoint's richer expert
 * record) and from `ExpertProfile` (the experts endpoint's directory entry).
 */
final readonly class RankingExpert
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $twitter,
        public ?string $publishedAt,
    ) {}

    /**
     * Fold the envelope's parallel maps into one entry per expert.
     *
     * Keyed off `expert_pub`, which is the map that carries every contributing
     * expert; the other two are looked up against it.
     *
     * @return array<array-key, self>
     */
    public static function mapFrom(Payload $payload): array
    {
        $published = $payload->stringMap('expert_pub');
        // The spec calls this `expert_name`; the live payload sends `expert_names`.
        $names = $payload->stringMap('expert_names');
        $twitter = $payload->stringMap('expert_twitter');

        $experts = [];

        foreach ($published as $id => $publishedAt) {
            $experts[$id] = new self(
                id: (int) $id,
                name: $names[$id] ?? null,
                twitter: $twitter[$id] ?? null,
                publishedAt: $publishedAt,
            );
        }

        return $experts;
    }
}

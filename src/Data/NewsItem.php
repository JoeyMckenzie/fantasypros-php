<?php

declare(strict_types=1);

namespace FantasyPros\Data;

/**
 * One story from the news feed.
 */
final readonly class NewsItem implements ApiDataContract
{
    /**
     * @param  list<string>  $categories  free-form tags such as `News` or `Injury`;
     *                                    these are not the `category` request filter's
     *                                    vocabulary and are not enumerated in the spec
     */
    public function __construct(
        public int $id,
        public string $created,
        public string $createdFormatted,
        public string $author,
        public int $playerId,
        public string $teamId,
        public string $title,
        public string $sportId,
        public array $categories,
        public string $link,
        public string $description,
        public string $impact,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            id: $payload->int('id'),
            created: $payload->string('created'),
            // `created_formated` is the API's spelling, not a typo here.
            createdFormatted: $payload->string('created_formated'),
            author: $payload->string('author'),
            playerId: $payload->int('player_id'),
            teamId: $payload->string('team_id'),
            title: $payload->string('title'),
            sportId: $payload->string('sport_id'),
            categories: $payload->strings('categories'),
            link: $payload->string('link'),
            description: $payload->string('desc'),
            impact: $payload->string('impact'),
        );
    }
}

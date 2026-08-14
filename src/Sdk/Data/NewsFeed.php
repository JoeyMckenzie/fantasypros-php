<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Data;

use Fantasy\Sdk\Enums\Sport;
use Saloon\Http\Response;

/**
 * The news endpoint envelope.
 */
final readonly class NewsFeed
{
    /**
     * @param  list<NewsItem>  $items
     */
    public function __construct(
        public Sport $sport,
        public string $title,
        public string $description,
        public int $count,
        public array $items,
        public ApiLimits $limits,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $payload = Payload::fromResponse($response);

        return new self(
            sport: Sport::from($payload->string('sport')),
            title: $payload->string('title'),
            description: $payload->string('description'),
            count: $payload->int('count'),
            items: array_map(
                NewsItem::fromPayload(...),
                $payload->objects('items'),
            ),
            limits: ApiLimits::fromPayload($payload),
        );
    }
}

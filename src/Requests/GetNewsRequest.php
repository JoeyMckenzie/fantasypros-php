<?php

declare(strict_types=1);

namespace FantasyPros\Requests;

use FantasyPros\Data\Envelopes\NewsFeed;
use FantasyPros\Enums\NewsCategory;
use FantasyPros\Enums\NewsOrder;
use FantasyPros\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/news: the player news feed.
 */
final class GetNewsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  ?int  $playerId  restrict the feed to one FantasyPros player
     * @param  ?int  $limit  items to return; the endpoint caps this at 100 and
     *                       defaults to 25 when omitted
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly ?int $playerId = null,
        private readonly ?int $limit = null,
        private readonly ?NewsCategory $category = null,
        private readonly ?NewsOrder $orderBy = null,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/news', $this->sport->pathSegment());
    }

    #[Override]
    public function createDtoFromResponse(Response $response): NewsFeed
    {
        return NewsFeed::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'fpid' => $this->playerId,
            'limit' => $this->limit,
            'category' => $this->category?->value,
            // The endpoint's own defaults for limit and order_by are left to it
            // rather than restated here, where they would silently go stale.
            'order_by' => $this->orderBy?->value,
        ];

        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

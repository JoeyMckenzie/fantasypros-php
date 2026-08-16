<?php

declare(strict_types=1);

namespace FantasyPros\Concerns;

use FantasyPros\Data\Envelopes\NewsFeed;
use FantasyPros\Enums\NewsCategory;
use FantasyPros\Enums\NewsOrder;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetNewsRequest;
use Saloon\Http\Connector;

/**
 * The player news feed, in the API's own vocabulary.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsNewsEndpoints
{
    /**
     * GET /{sport}/news: the player news feed.
     *
     * The endpoint caps `$limit` at 100 and defaults to 25 when omitted; those
     * defaults are left to it rather than restated here, where they would
     * silently go stale.
     */
    public function news(
        Sport $sport,
        ?int $playerId = null,
        ?int $limit = null,
        ?NewsCategory $category = null,
        ?NewsOrder $orderBy = null,
    ): NewsFeed {
        $request = new GetNewsRequest(
            sport: $sport,
            playerId: $playerId,
            limit: $limit,
            category: $category,
            orderBy: $orderBy,
        );

        return $request->createDtoFromResponse($this->send($request));
    }
}

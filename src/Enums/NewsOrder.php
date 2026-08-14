<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * The `order_by` query parameter on the news endpoint, choosing which
 * timestamp orders the feed. The endpoint defaults to `created`.
 */
enum NewsOrder: string
{
    case Updated = 'updated';

    case Created = 'created';
}

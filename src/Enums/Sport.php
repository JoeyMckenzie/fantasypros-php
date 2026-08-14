<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * The sports the API is keyed by.
 *
 * Scope is NFL-first; the rest exist so the `{sport}` path parameter is typed
 * rather than stringly, not because they are exercised.
 */
enum Sport: string
{
    case Nfl = 'NFL';

    case Mlb = 'MLB';

    case Nba = 'NBA';

    case Nhl = 'NHL';

    case Pga = 'PGA';

    case Ncaaf = 'NCAAF';

    /**
     * The `{sport}` path segment.
     *
     * The spec enumerates these uppercase but every code sample in it calls
     * the lowercase route, e.g. `/nfl/players`.
     */
    public function pathSegment(): string
    {
        return mb_strtolower($this->value);
    }
}

<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * The `details` query parameter on the compare-players endpoint, controlling
 * which lookup blocks the response carries alongside the rankings.
 */
enum ComparisonDetails: string
{
    case Players = 'players';

    case Experts = 'experts';

    case All = 'all';
}

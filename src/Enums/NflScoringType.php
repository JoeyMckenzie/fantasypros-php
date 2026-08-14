<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * NFL scoring formats, per `NFLScoringTypes` in the spec.
 */
enum NflScoringType: string
{
    case Standard = 'STD';

    case Ppr = 'PPR';

    case Half = 'HALF';
}

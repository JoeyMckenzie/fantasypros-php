<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * An NFL injury status.
 *
 * The live endpoint also returns an empty status for players who appear only on
 * the practice report, which is why `NflInjury` keeps the raw string and offers
 * this enum through a non-throwing accessor rather than hydrating into it.
 */
enum NflInjuryStatus: string
{
    case CovidIr = 'COV-IR';

    case Doubtful = 'Doubtful';

    case InjuredReserve = 'IR';

    case NotStarting = 'Not Starting';

    case Out = 'OUT';

    case PhysicallyUnableToPerform = 'PUP';

    case Questionable = 'Questionable';

    case Suspended = 'Suspended';
}

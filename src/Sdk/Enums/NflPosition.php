<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Enums;

/**
 * NFL positions, per `NFLPositions` in the spec.
 *
 * Covers more than the fantasy-relevant slots because the API accepts team
 * (`TQB`, `TRB`, …), coaching (`HC`) and IDP breakdown positions too.
 */
enum NflPosition: string
{
    case All = 'ALL';

    case Flex = 'FLX';

    case OffensivePlayer = 'OP';

    case Quarterback = 'QB';

    case RunningBack = 'RB';

    case WideReceiver = 'WR';

    case TightEnd = 'TE';

    case Kicker = 'K';

    case Defense = 'DST';

    case IndividualDefensivePlayer = 'IDP';

    case DefensiveLine = 'DL';

    case Linebacker = 'LB';

    case DefensiveBack = 'DB';

    case TeamKicker = 'TK';

    case TeamQuarterback = 'TQB';

    case TeamRunningBack = 'TRB';

    case TeamWideReceiver = 'TWR';

    case TeamTightEnd = 'TTE';

    case TeamOffensiveLine = 'TOL';

    case HeadCoach = 'HC';

    case Punter = 'P';

    case ReturnKicker = 'RK';

    case OffensiveTackle = 'OT';

    case OffensiveGuard = 'OG';

    case InteriorOffensiveLine = 'IOL';

    case Center = 'C';

    case InteriorDefensiveLine = 'IDL';

    case DefensiveEnd = 'DE';

    case DefensiveTackle = 'DT';

    case Cornerback = 'CB';

    case Safety = 'S';
}

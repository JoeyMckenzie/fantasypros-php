<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * NFL ranking types, per `NFLRankingTypes` in the spec.
 *
 * The spec's enum lists `PRO` and `PROSPECT` twice each; a PHP enum cannot
 * carry duplicate cases, so each appears once here.
 */
enum NflRankingType: string
{
    case WaiverWire = 'WW';

    case Waiver = 'WAIVER';

    case RestOfSeason = 'ROS';

    case Draft = 'DRAFT';

    case Preseason = 'PRESEASON';

    case Sleepers = 'SLEEPERS';

    case AverageDraftPosition = 'ADP';

    case Best = 'BEST';

    case Prospect = 'PROSPECT';

    case Pro = 'PRO';

    case Devy = 'DEVY';

    case Rookies = 'ROOKIES';

    case DynastyAverageDraftPosition = 'DYNADP';

    case RookieAverageDraftPosition = 'RKADP';

    case BestBallAverageDraftPosition = 'BESTADP';

    case Dynasty = 'DYNASTY';

    case Pre = 'PRE';

    case Drafters = 'DRAFTERS';

    case Mock = 'MOCK';
}

<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Enums;

/**
 * Fantasy sites and data providers whose player IDs the players endpoint can
 * fold into each player object, via the colon-delimited `external_ids` param.
 */
enum ExternalIdSource: string
{
    case Yahoo = 'yahoo';

    case Espn = 'espn';

    case Cbs = 'cbs';

    case Rts = 'rts';

    case FanDuel = 'fanduel';

    case DraftKings = 'draftkings';

    case FantasyDraft = 'fantasydraft';

    case RotoGrinders = 'rotogrinders';

    case Fleaflicker = 'fleaflicker';

    case RotoWire = 'rotowire';

    case Rotoworld = 'rotoworld';

    case NumberFire = 'numberfire';

    case Fantrax = 'fantrax';

    case Nfl = 'nfl';

    case Mfl = 'mfl';

    case Tsn = 'tsn';

    case OnRoto = 'onroto';

    case XmlTeam = 'xmlteam';

    case Ffwc = 'ffwc';

    case Mlbam = 'mlbam';

    case Nba = 'nba';
}

<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * The `experts` query parameter on the consensus-rankings endpoint, asking the
 * response to describe the experts behind the consensus.
 */
enum ExpertsDetail: string
{
    case Show = 'show';

    case Available = 'available';
}

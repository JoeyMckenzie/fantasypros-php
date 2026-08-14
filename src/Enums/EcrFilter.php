<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * Whether to include or exclude players present in consensus rankings,
 * per the `ecr` query parameter on the players endpoint.
 */
enum EcrFilter: string
{
    case Included = 'included';

    case Excluded = 'excluded';
}

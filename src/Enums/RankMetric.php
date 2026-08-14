<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * The metric blocks inside a ranked player's nested `rank` object.
 *
 * Only `Consensus` is always present. `Minimum`/`Maximum` arrive when the
 * request asks for `range`, and `Average`/`StandardDeviation` when it asks for
 * `rankstats` -- the spec describes neither the nesting nor these keys.
 */
enum RankMetric: string
{
    case Consensus = 'ECR';

    case Minimum = 'ECR_MIN';

    case Maximum = 'ECR_MAX';

    case Average = 'ECR_AVG';

    case StandardDeviation = 'ECR_STD';
}

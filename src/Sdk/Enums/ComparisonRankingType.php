<?php

declare(strict_types=1);

namespace Fantasy\Sdk\Enums;

/**
 * The `ranking_type` query parameter on the compare-players endpoint.
 *
 * Distinct from NflRankingType: this endpoint takes its own lowercase, much
 * shorter set rather than the full ranking-type vocabulary.
 */
enum ComparisonRankingType: string
{
    case Draft = 'draft';

    case Weekly = 'weekly';

    case RestOfSeason = 'ros';
}

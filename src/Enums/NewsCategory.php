<?php

declare(strict_types=1);

namespace FantasyPros\Enums;

/**
 * The `category` query parameter on the news endpoint.
 *
 * The spec lists `null` alongside these values; that is the "no filter" case,
 * so it is expressed by omitting the parameter rather than by an enum case.
 */
enum NewsCategory: string
{
    case Injury = 'injury';

    case Recap = 'recap';

    case Transaction = 'transaction';

    case Rumor = 'rumor';

    case Breaking = 'breaking';
}

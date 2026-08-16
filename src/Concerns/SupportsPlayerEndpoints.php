<?php

declare(strict_types=1);

namespace FantasyPros\Concerns;

use DateTimeImmutable;
use FantasyPros\Data\Envelopes\PlayerCollection;
use FantasyPros\Enums\EcrFilter;
use FantasyPros\Enums\ExternalIdSource;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetPlayersRequest;
use Saloon\Http\Connector;

/**
 * The players endpoint, in the API's own vocabulary.
 *
 * Every method here hydrates through the request's `createDtoFromResponse`
 * rather than `Response::dto()`, which is typed `mixed` and would erase the
 * envelope type at the call site.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsPlayerEndpoints
{
    /**
     * GET /{sport}/players: rosters and player metadata.
     *
     * @param  list<ExternalIdSource>  $externalIds  sites whose player IDs to fold in
     */
    public function players(
        Sport $sport,
        ?int $playerId = null,
        ?DateTimeImmutable $updatedSince = null,
        ?EcrFilter $ecr = null,
        array $externalIds = [],
        bool $withPositionRank = false,
    ): PlayerCollection {
        $request = new GetPlayersRequest(
            sport: $sport,
            playerId: $playerId,
            updatedSince: $updatedSince,
            ecr: $ecr,
            externalIds: $externalIds,
            withPositionRank: $withPositionRank,
        );

        return $request->createDtoFromResponse($this->send($request));
    }
}

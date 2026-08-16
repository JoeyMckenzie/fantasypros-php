<?php

declare(strict_types=1);

namespace FantasyPros\Requests;

use DateTimeImmutable;
use FantasyPros\Data\Envelopes\PlayerCollection;
use FantasyPros\Enums\EcrFilter;
use FantasyPros\Enums\ExternalIdSource;
use FantasyPros\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /{sport}/players: rosters and player metadata.
 */
final class GetPlayersRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  list<ExternalIdSource>  $externalIds  sites whose player IDs to fold in
     */
    public function __construct(
        private readonly Sport $sport,
        private readonly ?int $playerId = null,
        private readonly ?DateTimeImmutable $updatedSince = null,
        private readonly ?EcrFilter $ecr = null,
        private readonly array $externalIds = [],
        private readonly bool $withPositionRank = false,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/%s/players', $this->sport->pathSegment());
    }

    #[Override]
    public function createDtoFromResponse(Response $response): PlayerCollection
    {
        return PlayerCollection::fromResponse($response);
    }

    /**
     * @return array<string, string|int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'player' => $this->playerId,
            'update' => $this->updatedSince?->format('Y-m-d'),
            'ecr' => $this->ecr?->value,
            'external_ids' => $this->externalIds === []
                ? null
                : implode(':', array_column($this->externalIds, 'value')),
            // The parameter is a single-valued enum; presence is the signal.
            'show' => $this->withPositionRank ? 'pos_rank' : null,
        ];

        // Send only what was asked for. An empty `?player=` isn't the same as
        // omitting it.
        return array_filter($query, static fn (string|int|null $value): bool => $value !== null);
    }
}

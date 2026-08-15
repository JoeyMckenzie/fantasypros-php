<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Requests;

use DateTimeImmutable;
use FantasyPros\Data\Api\ApiLimits;
use FantasyPros\Data\Api\NflPlayer;
use FantasyPros\Data\Envelopes\PlayerCollection;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\EcrFilter;
use FantasyPros\Enums\ExternalIdSource;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetPlayersRequest;
use FantasyPros\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetPlayersRequest::class)]
#[CoversClass(PlayerCollection::class)]
#[CoversClass(NflPlayer::class)]
#[CoversClass(ApiLimits::class)]
#[CoversClass(Payload::class)]
final class GetPlayersRequestTest extends RequestTestCase
{
    #[Test]
    public function it_builds_the_players_path_for_the_sport(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json/nfl/players',
            $this->uriFor(new GetPlayersRequest(Sport::Nfl)),
        );
    }

    #[Test]
    public function it_sends_no_query_string_when_nothing_is_asked_for(): void
    {
        self::assertSame('', $this->queryFor(new GetPlayersRequest(Sport::Nfl)));
    }

    #[Test]
    public function it_sends_every_supported_filter(): void
    {
        $request = new GetPlayersRequest(
            Sport::Nfl,
            playerId: 17240,
            updatedSince: new DateTimeImmutable('2025-05-13 14:22:01'),
            ecr: EcrFilter::Included,
            externalIds: [ExternalIdSource::Yahoo, ExternalIdSource::Espn, ExternalIdSource::Cbs],
            withPositionRank: true,
        );

        self::assertSame(
            'player=17240&update=2025-05-13&ecr=included&external_ids=yahoo%3Aespn%3Acbs&show=pos_rank',
            $this->queryFor($request),
        );
    }

    #[Test]
    public function it_omits_the_position_rank_flag_when_not_requested(): void
    {
        $request = new GetPlayersRequest(Sport::Nfl, withPositionRank: false);

        self::assertStringNotContainsString('show', $this->queryFor($request));
    }

    /**
     * Unset filters are dropped outright, not sent as empty parameters. An
     * `?player=` is not the same request as omitting `player`.
     */
    #[Test]
    public function it_registers_no_empty_parameters(): void
    {
        self::assertSame([], $this->queryParametersFor(new GetPlayersRequest(Sport::Nfl)));

        self::assertSame(
            ['player' => 17240],
            $this->queryParametersFor(new GetPlayersRequest(Sport::Nfl, playerId: 17240)),
        );
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_player_collection(): void
    {
        $collection = $this->dtoFrom(
            new GetPlayersRequest(Sport::Nfl, playerId: 17240, withPositionRank: true),
            'nfl/players',
        );

        self::assertInstanceOf(PlayerCollection::class, $collection);
        self::assertSame(Sport::Nfl, $collection->sport);
        self::assertSame(2026, $collection->season);
        self::assertSame(0, $collection->week);
        self::assertCount(1, $collection->players);
    }

    #[Test]
    public function a_recorded_player_hydrates_every_mapped_field(): void
    {
        $collection = $this->dtoFrom(
            new GetPlayersRequest(Sport::Nfl, playerId: 17240, withPositionRank: true),
            'nfl/players',
        );

        self::assertInstanceOf(PlayerCollection::class, $collection);

        $player = $collection->players[0];

        self::assertSame(17240, $player->id);
        self::assertSame('Saquon Barkley', $player->name);
        self::assertSame('S. Barkley', $player->shortName);
        self::assertSame('Saquon', $player->firstName);
        self::assertSame('Barkley', $player->lastName);
        self::assertSame('Barkley, Saquon', $player->reverseName);
        self::assertSame('RB', $player->positionId);
        self::assertSame(['RB'], $player->positions);
        self::assertSame('PHI', $player->teamId);
        self::assertSame('https://www.fantasypros.com/nfl/players/saquon-barkley.php', $player->profileUrl);
        self::assertSame('9811b753-347c-467a-b3cb-85937e71e2b9', $player->sportsdataPlayerId);
        self::assertSame(16, $player->ecrRank);
        self::assertSame(14, $player->adpRank);
        self::assertSame(26, $player->pprEcrRank);
        self::assertSame(16, $player->halfPprEcrRank);
        self::assertSame(14, $player->pprAdpRank);
        self::assertSame('1997-02-09', $player->birthdate);
        self::assertSame(29, $player->age);
        self::assertSame(2018, $player->draftClass);
    }

    #[Test]
    public function a_players_position_is_available_as_an_enum(): void
    {
        $collection = $this->dtoFrom(
            new GetPlayersRequest(Sport::Nfl, playerId: 17240, withPositionRank: true),
            'nfl/players',
        );

        self::assertInstanceOf(PlayerCollection::class, $collection);
        self::assertSame(NflPosition::RunningBack, $collection->players[0]->position());
    }

    #[Test]
    public function the_free_tier_quota_is_carried_on_the_collection(): void
    {
        $collection = $this->dtoFrom(
            new GetPlayersRequest(Sport::Nfl, playerId: 17240, withPositionRank: true),
            'nfl/players',
        );

        self::assertInstanceOf(PlayerCollection::class, $collection);
        self::assertTrue($collection->limits->limited);
        self::assertSame('free', $collection->limits->tier);
        self::assertSame(10, $collection->limits->limit);
    }
}

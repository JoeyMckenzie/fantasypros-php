<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Requests;

use FantasyPros\Data\Api\NewsItem;
use FantasyPros\Data\Envelopes\NewsFeed;
use FantasyPros\Data\Infrastructure\ApiLimits;
use FantasyPros\Enums\NewsCategory;
use FantasyPros\Enums\NewsOrder;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetNewsRequest;
use FantasyPros\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetNewsRequest::class)]
#[CoversClass(NewsFeed::class)]
#[CoversClass(NewsItem::class)]
#[CoversClass(ApiLimits::class)]
#[CoversClass(NewsCategory::class)]
#[CoversClass(NewsOrder::class)]
final class GetNewsRequestTest extends RequestTestCase
{
    #[Test]
    public function it_builds_the_news_path_for_the_sport(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json/nfl/news',
            $this->uriFor(new GetNewsRequest(Sport::Nfl)),
        );
    }

    #[Test]
    public function it_sends_every_supported_option(): void
    {
        $request = new GetNewsRequest(
            Sport::Nfl,
            playerId: 17240,
            limit: 5,
            category: NewsCategory::Injury,
            orderBy: NewsOrder::Updated,
        );

        self::assertSame(
            'fpid=17240&limit=5&category=injury&order_by=updated',
            $this->queryFor($request),
        );
    }

    /**
     * The endpoint's own defaults (limit 25, order_by created) are left to it,
     * so an unconfigured request sends nothing at all.
     */
    #[Test]
    public function it_registers_no_parameters_when_nothing_is_asked_for(): void
    {
        self::assertSame([], $this->queryParametersFor(new GetNewsRequest(Sport::Nfl)));
    }

    #[Test]
    public function it_registers_only_the_options_that_were_set(): void
    {
        self::assertSame(
            ['category' => 'recap'],
            $this->queryParametersFor(new GetNewsRequest(Sport::Nfl, category: NewsCategory::Recap)),
        );
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_feed(): void
    {
        $feed = $this->recordedFeed();

        self::assertSame(Sport::Nfl, $feed->sport);
        self::assertSame('Fantasy Player News', $feed->title);
        self::assertStringStartsWith('Breaking Fantasy Football player news', $feed->description);
        self::assertSame(5, $feed->count);
        self::assertCount(5, $feed->items);
    }

    #[Test]
    public function every_field_of_a_news_item_is_mapped(): void
    {
        $item = $this->recordedFeed()->items[0];

        self::assertSame(602359, $item->id);
        self::assertSame('2026-08-14 19:37:04', $item->created);
        // The API spells the key `created_formated`; the DTO does not.
        self::assertSame('Fri, Aug 14th 7:37pm UTC', $item->createdFormatted);
        self::assertSame('Ari Koslow', $item->author);
        self::assertSame(18269, $item->playerId);
        self::assertSame('GB', $item->teamId);
        self::assertSame('Josh Jacobs (groin) could return next week', $item->title);
        self::assertSame('NFL', $item->sportId);
        self::assertSame(['Commentary', 'News', 'Injury'], $item->categories);
        self::assertSame(
            'https://www.fantasypros.com/nfl/news/602359/josh-jacobs-groin-could-return-next-week.php',
            $item->link,
        );
        self::assertStringStartsWith('Packers coach Matt LaFleur said', $item->description);
        self::assertStringStartsWith('LaFleur clarified', $item->impact);
    }

    /**
     * The feed carries the same quota envelope the players endpoint does, which
     * the spec documents on neither.
     */
    #[Test]
    public function the_feed_reports_the_tier_limits(): void
    {
        $limits = $this->recordedFeed()->limits;

        self::assertTrue($limits->limited);
        self::assertSame('free', $limits->tier);
        self::assertSame(10, $limits->limit);
    }

    private function recordedFeed(): NewsFeed
    {
        $feed = $this->dtoFrom(new GetNewsRequest(Sport::Nfl, limit: 5), 'nfl/news');

        self::assertInstanceOf(NewsFeed::class, $feed);

        return $feed;
    }
}

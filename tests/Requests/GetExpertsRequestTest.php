<?php

declare(strict_types=1);

namespace FantasyPros\Tests\Requests;

use FantasyPros\Data\Api\ExpertProfile;
use FantasyPros\Data\Envelopes\ExpertDirectory;
use FantasyPros\Data\Infrastructure\ApiLimits;
use FantasyPros\Data\Infrastructure\Payload;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\Requests\GetExpertsRequest;
use FantasyPros\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GetExpertsRequest::class)]
#[CoversClass(ExpertDirectory::class)]
#[CoversClass(ExpertProfile::class)]
#[CoversClass(ApiLimits::class)]
final class GetExpertsRequestTest extends RequestTestCase
{
    /**
     * The season sits between the sport and the nested `rankings/experts`
     * segments rather than at the end of the path.
     */
    #[Test]
    public function it_builds_the_nested_path_from_the_sport_and_season(): void
    {
        self::assertSame(
            'https://api.fantasypros.com/public/v2/json/nfl/2026/rankings/experts',
            $this->uriFor(new GetExpertsRequest(Sport::Nfl, 2026)),
        );
    }

    #[Test]
    public function it_sends_every_supported_option(): void
    {
        $request = new GetExpertsRequest(
            Sport::Nfl,
            2026,
            NflPosition::Quarterback,
            NflRankingType::Draft,
            NflScoringType::Ppr,
            withOverallAccuracy: true,
        );

        self::assertSame(
            'position=QB&type=DRAFT&scoring=PPR&include_overall=true',
            $this->queryFor($request),
        );
    }

    /**
     * Position is optional here, unlike on the consensus-rankings route, so an
     * unconfigured request sends nothing at all.
     */
    #[Test]
    public function it_registers_no_parameters_when_nothing_is_asked_for(): void
    {
        self::assertSame([], $this->queryParametersFor(new GetExpertsRequest(Sport::Nfl, 2026)));
    }

    #[Test]
    public function the_overall_accuracy_toggle_is_omitted_rather_than_sent_as_false(): void
    {
        self::assertSame(
            ['include_overall' => 'true'],
            $this->queryParametersFor(new GetExpertsRequest(Sport::Nfl, 2026, withOverallAccuracy: true)),
        );

        self::assertSame(
            [],
            $this->queryParametersFor(new GetExpertsRequest(Sport::Nfl, 2026, withOverallAccuracy: false)),
        );
    }

    #[Test]
    public function it_registers_only_the_options_that_were_set(): void
    {
        self::assertSame(
            ['position' => 'QB'],
            $this->queryParametersFor(new GetExpertsRequest(Sport::Nfl, 2026, NflPosition::Quarterback)),
        );
    }

    #[Test]
    public function a_recorded_response_hydrates_into_a_directory(): void
    {
        $directory = $this->recordedDirectory();

        self::assertSame(Sport::Nfl, $directory->sport);
        self::assertSame(2025, $directory->season);
        self::assertSame(1, $directory->week);
        self::assertSame(2025, $directory->draftAccuracySeason);
        self::assertSame(2025, $directory->weeklyAccuracySeason);
        // Undocumented, alongside the per-expert map of the same name.
        self::assertSame(2024, $directory->lastWeeklyAccuracySeason);
    }

    #[Test]
    public function every_field_of_an_expert_is_mapped(): void
    {
        $funston = $this->recordedDirectory()->experts[0];

        self::assertSame(6, $funston->id);
        self::assertSame('Brandon Funston', $funston->name);
        self::assertSame('The Athletic', $funston->source);
        self::assertSame('https://theathletic.com', $funston->url);
        self::assertSame('BrandonFunston', $funston->twitter);
        self::assertSame('', $funston->bio);
        self::assertSame(['QB' => '2025-09-04 20:02:55'], $funston->positions);
    }

    /**
     * The wire key is `default`, singular, though the spec documents `defaults`.
     */
    #[Test]
    public function the_default_position_flags_are_mapped(): void
    {
        $funston = $this->recordedDirectory()->experts[0];

        self::assertSame(['QB' => true], $funston->defaults);
        self::assertTrue($funston->isDefaultFor('QB'));
        self::assertFalse($funston->isDefaultFor('RB'));
    }

    /**
     * None of the accuracy maps appear in the spec's expert schema. `ALL` is the
     * overall row alongside the per-position ones.
     */
    #[Test]
    public function the_undocumented_accuracy_maps_are_mapped(): void
    {
        $behrens = $this->recordedDirectory()->experts[1];

        self::assertSame('Andy Behrens', $behrens->name);
        self::assertSame(['ALL' => 170, 'QB' => 145], $behrens->draftAccuracy);
        self::assertSame(['QB' => 102, 'ALL' => 71], $behrens->weeklyAccuracy);
    }

    /**
     * The weekly maps are absent on some experts entirely, which reads as empty
     * rather than throwing.
     */
    #[Test]
    public function an_expert_without_weekly_accuracy_hydrates_anyway(): void
    {
        $funston = $this->recordedDirectory()->experts[0];

        self::assertSame(['ALL' => 178, 'QB' => 185], $funston->draftAccuracy);
        self::assertSame([], $funston->weeklyAccuracy);
        self::assertSame([], $funston->lastSeasonWeeklyAccuracy);
    }

    #[Test]
    public function it_reports_when_the_tier_truncated_the_directory(): void
    {
        $directory = $this->recordedDirectory();

        self::assertSame(189, $directory->count);
        self::assertCount(10, $directory->experts);
        self::assertTrue($directory->truncated());
        self::assertSame(10, $directory->limits->limit);
    }

    /**
     * The recorded directory is truncated, so the un-truncated case is pinned
     * here rather than left to a fixture that cannot show it.
     */
    #[Test]
    public function a_complete_directory_is_not_truncated(): void
    {
        self::assertFalse($this->directoryOf(count: 2, experts: 2)->truncated());
        self::assertTrue($this->directoryOf(count: 3, experts: 2)->truncated());
    }

    private function directoryOf(int $count, int $experts): ExpertDirectory
    {
        $profile = ExpertProfile::fromPayload(Payload::of([
            'expert_id' => 6,
            'name' => 'Brandon Funston',
            'source' => 'The Athletic',
        ]));

        return new ExpertDirectory(
            sport: Sport::Nfl,
            count: $count,
            season: 2025,
            week: 1,
            draftAccuracySeason: null,
            weeklyAccuracySeason: null,
            lastWeeklyAccuracySeason: null,
            experts: array_fill(0, $experts, $profile),
            limits: new ApiLimits(false, null, null),
        );
    }

    private function recordedDirectory(): ExpertDirectory
    {
        $directory = $this->dtoFrom(
            new GetExpertsRequest(Sport::Nfl, 2025, NflPosition::Quarterback, withOverallAccuracy: true),
            'nfl/experts',
        );

        self::assertInstanceOf(ExpertDirectory::class, $directory);

        return $directory;
    }
}

<?php

declare(strict_types=1);

namespace FantasyPros;

use DateTimeImmutable;
use FantasyPros\Data\Envelopes\ConsensusRankings;
use FantasyPros\Data\Envelopes\ExpertDirectory;
use FantasyPros\Data\Envelopes\InjuryReport;
use FantasyPros\Data\Envelopes\NewsFeed;
use FantasyPros\Data\Envelopes\PlayerCollection;
use FantasyPros\Data\Envelopes\PlayerComparison;
use FantasyPros\Data\Envelopes\RankingsCollection;
use FantasyPros\Enums\ComparisonDetails;
use FantasyPros\Enums\ComparisonRankingType;
use FantasyPros\Enums\EcrFilter;
use FantasyPros\Enums\ExpertsDetail;
use FantasyPros\Enums\ExternalIdSource;
use FantasyPros\Enums\NewsCategory;
use FantasyPros\Enums\NewsOrder;
use FantasyPros\Enums\NflPosition;
use FantasyPros\Enums\NflRankingType;
use FantasyPros\Enums\NflScoringType;
use FantasyPros\Enums\Sport;
use FantasyPros\Exceptions\MissingApiKeyException;
use FantasyPros\Requests\ComparePlayersRequest;
use FantasyPros\Requests\GetConsensusRankingsRequest;
use FantasyPros\Requests\GetExpertsRequest;
use FantasyPros\Requests\GetInjuriesRequest;
use FantasyPros\Requests\GetNewsRequest;
use FantasyPros\Requests\GetPlayersRequest;
use FantasyPros\Requests\GetRankingsRequest;
use Override;
use Saloon\Contracts\Authenticator;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;

final class FantasyProsConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use HasTimeout;

    public const string BASE_URL = 'https://api.fantasypros.com/public/v2/json';

    public const string API_KEY_HEADER = 'x-api-key';

    public const string API_KEY_ENV_VAR = 'FANTASYPROS_API_KEY';

    public function __construct(private readonly string $apiKey)
    {
        if (mb_trim($this->apiKey) === '') {
            throw MissingApiKeyException::blank();
        }

        $this->tries = 3;
        $this->retryInterval = 500;
        $this->useExponentialBackoff = true;
    }

    /**
     * Build a connector from the environment, reading FANTASYPROS_API_KEY.
     */
    public static function fromEnvironment(): self
    {
        $apiKey = $_SERVER[self::API_KEY_ENV_VAR] ?? $_ENV[self::API_KEY_ENV_VAR] ?? getenv(self::API_KEY_ENV_VAR);

        if (! is_string($apiKey) || mb_trim($apiKey) === '') {
            throw MissingApiKeyException::absentFromEnvironment(self::API_KEY_ENV_VAR);
        }

        return new self($apiKey);
    }

    /**
     * GET /{sport}/players -- rosters and player metadata.
     *
     * The endpoint methods below mirror their request's constructor exactly and
     * hydrate through `createDtoFromResponse` rather than `Response::dto()`,
     * which is typed `mixed` and would erase the envelope type at the call site.
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

    /**
     * GET /{sport}/{season}/rankings -- rankings across ranking types and positions.
     *
     * @param  list<int>  $expertIds  restrict the rankings to these experts
     */
    public function rankings(
        Sport $sport,
        int $season,
        ?int $week = null,
        ?int $playerId = null,
        array $expertIds = [],
        bool $minimal = false,
        bool $withRange = false,
        bool $withRankStats = false,
        bool $includeDrafters = false,
    ): RankingsCollection {
        $request = new GetRankingsRequest(
            sport: $sport,
            season: $season,
            week: $week,
            playerId: $playerId,
            expertIds: $expertIds,
            minimal: $minimal,
            withRange: $withRange,
            withRankStats: $withRankStats,
            includeDrafters: $includeDrafters,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /{sport}/{season}/consensus-rankings -- the expert consensus for one position.
     *
     * @param  list<int>  $expertIds  restrict the consensus to these experts
     */
    public function consensusRankings(
        Sport $sport,
        int $season,
        NflPosition $position,
        ?NflRankingType $rankingType = null,
        ?NflScoringType $scoring = null,
        ?int $week = null,
        array $expertIds = [],
        ?ExpertsDetail $experts = null,
        bool $includeIndividualDefensivePlayers = false,
    ): ConsensusRankings {
        $request = new GetConsensusRankingsRequest(
            sport: $sport,
            season: $season,
            position: $position,
            rankingType: $rankingType,
            scoring: $scoring,
            week: $week,
            expertIds: $expertIds,
            experts: $experts,
            includeIndividualDefensivePlayers: $includeIndividualDefensivePlayers,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /{sport}/{season}/rankings/experts -- profiles of the ranking experts.
     */
    public function experts(
        Sport $sport,
        int $season,
        ?NflPosition $position = null,
        ?NflRankingType $rankingType = null,
        ?NflScoringType $scoring = null,
        bool $withOverallAccuracy = false,
    ): ExpertDirectory {
        $request = new GetExpertsRequest(
            sport: $sport,
            season: $season,
            position: $position,
            rankingType: $rankingType,
            scoring: $scoring,
            withOverallAccuracy: $withOverallAccuracy,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /{sport}/injuries -- injury statuses and, for the NFL, the practice report.
     *
     * @param  list<string>  $teamIds  pro team codes, e.g. `SF`
     * @param  list<int>  $playerIds  FantasyPros player IDs
     */
    public function injuries(
        Sport $sport,
        ?int $year = null,
        ?int $week = null,
        array $teamIds = [],
        array $playerIds = [],
        bool $includeMinors = false,
        bool $includeProbabilities = false,
    ): InjuryReport {
        $request = new GetInjuriesRequest(
            sport: $sport,
            year: $year,
            week: $week,
            teamIds: $teamIds,
            playerIds: $playerIds,
            includeMinors: $includeMinors,
            includeProbabilities: $includeProbabilities,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /{sport}/news -- the player news feed.
     */
    public function news(
        Sport $sport,
        ?int $playerId = null,
        ?int $limit = null,
        ?NewsCategory $category = null,
        ?NewsOrder $orderBy = null,
    ): NewsFeed {
        $request = new GetNewsRequest(
            sport: $sport,
            playerId: $playerId,
            limit: $limit,
            category: $category,
            orderBy: $orderBy,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /{sport}/compare-players -- head-to-head expert ranking comparison.
     *
     * @param  list<int>  $playerIds  FantasyPros player IDs to compare
     * @param  list<int>  $expertIds  restrict the comparison to these experts
     */
    public function comparePlayers(
        Sport $sport,
        array $playerIds,
        NflPosition $position,
        ?ComparisonRankingType $rankingType = null,
        ?ComparisonDetails $details = null,
        array $expertIds = [],
        ?int $year = null,
        ?int $week = null,
    ): PlayerComparison {
        $request = new ComparePlayersRequest(
            sport: $sport,
            playerIds: $playerIds,
            position: $position,
            rankingType: $rankingType,
            details: $details,
            expertIds: $expertIds,
            year: $year,
            week: $week,
        );

        return $request->createDtoFromResponse($this->send($request));
    }

    #[Override]
    public function resolveBaseUrl(): string
    {
        return self::BASE_URL;
    }

    /**
     * Overrides HasTimeout's default. Not #[Override] -- the trait is used by
     * this class, so there is no inherited method being replaced.
     */
    public function getConnectTimeout(): float
    {
        return 10;
    }

    public function getRequestTimeout(): float
    {
        return 30;
    }

    /**
     * Decide whether a failed attempt is worth repeating.
     */
    #[Override]
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        // Never got a response at all -- DNS, TLS, timeout. Worth another go.
        if ($exception instanceof FatalRequestException) {
            return true;
        }

        $status = $exception->getResponse()->status();

        // Only rate limiting and server faults are transient. A 401 (bad key)
        // or 400 (bad parameter) fails identically however often we ask.
        return $status === 429 || $status >= 500;
    }

    #[Override]
    protected function defaultAuth(): Authenticator
    {
        return new HeaderAuthenticator($this->apiKey, self::API_KEY_HEADER);
    }
}

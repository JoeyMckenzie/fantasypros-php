---
id: FANTASY-16
title: Domain-language endpoint methods on the connector
status: Done
assignee:
  - '@claude'
created_date: '2026-08-15 22:51'
updated_date: '2026-08-15 22:54'
labels:
  - sdk
  - dx
milestone: SDK v0.1.0
dependencies:
  - FANTASY-15
references:
  - 'https://github.com/ohdearapp/ohdear-php-sdk'
modified_files:
  - src/FantasyProsConnector.php
  - src/Concerns/
  - deptrac.php
  - examples/
priority: high
ordinal: 16000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Calling the SDK required going through Saloon's plumbing for every request: construct a Request, `send()` it, call `->dto()`, then narrow the `mixed` result with an `assert()` before the DTO could be used. Three lines and a runtime assertion to reach a value the library already knew the type of.

The Oh Dear PHP SDK (same Saloon base) reads instead as `$ohDear->monitors()` — the connector itself carries methods named after the API's own vocabulary, and the Saloon layer moves inside the library rather than being every caller's problem.

Adopting that shape here means a consumer names an endpoint and receives a typed envelope, never touching a Request class or `->dto()`.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Every implemented endpoint is reachable as a method on FantasyProsConnector named after the API's own vocabulary
- [x] #2 Each method returns its concrete envelope type, so callers need no assert() or instanceof narrowing
- [x] #3 Method parameters mirror their request's constructor, with no invented defaults or renamed arguments
- [x] #4 The examples use the connector methods, with no send(new Request) or ->dto() remaining
- [x] #5 The full gate passes, including PHPStan level max with no suppression, cast or inline @var
<!-- AC:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## How Oh Dear does it, and the one part that does not port

`OhDear` **is** the Saloon connector — there is no wrapper object. Domain methods live directly on it, composed from 27 `Supports*Endpoints` traits under a `Concerns/` namespace, each method being the same shape:

```php
public function me(): User
{
    $request = new MeRequest;

    return $this->send($request)->dto();
}
```

The concrete return type on the *method* is what gives callers their type; `dto()` itself returns `mixed`.

**Their exact line fails this project's gate.** Prototyped and confirmed: `Method playersViaDto() should return PlayerCollection but returns mixed` (`return.type`). Oh Dear runs **PHPStan level 5 with a baseline**; this project runs **level max with 100% type coverage**, and `phpstan.neon` forbids papering over it with `assert()`, an inline `@var`, or a cast.

The fix was already in the codebase: every request declares `createDtoFromResponse(Response $response): ConcreteEnvelope`. Calling that instead of `Response::dto()` is concretely typed with nothing suppressed:

```php
$request = new GetPlayersRequest(...);

return $request->createDtoFromResponse($this->send($request));
```

## Structure: six traits under `FantasyPros\Concerns`

Methods were first written directly on the connector, then moved into `Supports*Endpoints` traits at the maintainer's request, mirroring Oh Dear:

| Trait | Methods |
|---|---|
| `SupportsPlayerEndpoints` | `players()` |
| `SupportsRankingEndpoints` | `rankings()`, `consensusRankings()` |
| `SupportsExpertEndpoints` | `experts()` |
| `SupportsInjuryEndpoints` | `injuries()` |
| `SupportsNewsEndpoints` | `news()` |
| `SupportsComparisonEndpoints` | `comparePlayers()` |

The two ranking endpoints share a trait because they answer the same question at different resolutions. `FantasyProsConnector` is back to 111 lines of pure Saloon plumbing — auth, timeouts, retry — with no endpoint knowledge.

Each trait carries `@mixin Connector` so `$this->send()` resolves when the trait is read on its own.

## Deptrac had to be widened, and this is the trap

The `connector` layer was a `ClassLikeConfig` pinned to `FantasyProsConnector::class`. Traits in a new directory are **not** matched by that, so they would have sat outside every rule — invisible to the layering rather than violating it, which is the failure mode that looks green. `DirectoryConfig::create('Concerns')` was added to the same layer, and `deptrac debug:layer connector` now lists all seven class-likes (six traits plus the connector). Any future `Concerns` addition is covered automatically; a *different* new directory would not be.

## Shape of the parameters

Method parameters mirror each request's constructor exactly — same names, same order, same defaults. In particular `$sport` stays required rather than defaulting to `Sport::Nfl`, because the DTOs are NFL-shaped and inventing a default would assert something the library does not yet support.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Endpoint methods now live in six `Supports*Endpoints` traits under `FantasyPros\Concerns`, composed onto `FantasyProsConnector` — the Oh Dear structure. Callers name an endpoint and get a typed envelope, never a Request class or `->dto()`:

```php
// before
$response = $connector->send(new GetPlayersRequest(Sport::Nfl, withPositionRank: true));
$players = $response->dto();
assert($players instanceof PlayerCollection, 'Response is not a player collection.');

// after
$players = $connector->players(sport: Sport::Nfl, withPositionRank: true);
```

The `assert()` lines disappeared from all seven examples because the type is now static rather than a runtime claim. The connector itself is back to 111 lines of pure Saloon plumbing with no endpoint knowledge.

**The key deviation from Oh Dear:** they write `return $this->send($request)->dto();`, which returns `mixed` and fails this project's PHPStan level max — they run level 5 with a baseline. Using each request's existing `createDtoFromResponse(): ConcreteEnvelope` keeps it fully typed with nothing suppressed, no cast and no inline `@var`.

**Deptrac needed widening, and quietly.** Its `connector` layer was pinned to `FantasyProsConnector::class` by `ClassLikeConfig`, which does not match traits in a new directory — they would have fallen outside every rule and still reported green. Adding `DirectoryConfig::create('Concerns')` fixed it; `debug:layer connector` now lists all seven class-likes. Worth remembering for any future top-level directory.

Parameters mirror each request's constructor exactly, including keeping `$sport` required rather than defaulting to NFL.

Gate green: Pint, PHPStan level max over 74 files across `src`/`tests`/`examples`, Deptrac 0 violations, 191 tests. Examples smoke-tested live through the traits.
<!-- SECTION:FINAL_SUMMARY:END -->

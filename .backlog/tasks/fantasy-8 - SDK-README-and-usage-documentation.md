---
id: FANTASY-8
title: SDK README and usage documentation
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
updated_date: '2026-08-15 22:55'
labels:
  - sdk
milestone: SDK v0.1.0
dependencies:
  - FANTASY-3
  - FANTASY-4
  - FANTASY-5
  - FANTASY-6
  - FANTASY-7
  - FANTASY-16
  - FANTASY-17
priority: low
ordinal: 8000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
README covering install, obtaining/configuring the FantasyPros API key, and a usage example per endpoint group.

**Most of the raw material now exists.** FANTASY-17 added seven runnable examples under `examples/`, one per endpoint, each verified against the live API. The README should draw on those rather than inventing snippets from type signatures — and where it repeats one, it should match, so a reader who copies from the README and a reader who runs the example see the same thing.

**Every snippet must use the connector's endpoint methods** (FANTASY-16), never `send(new SomeRequest(...))->dto()`. The whole point of that work was that consumers do not touch Saloon; a README showing the old three-line form would teach the wrong API:

```php
$connector = FantasyProsConnector::fromEnvironment();
$players = $connector->players(sport: Sport::Nfl, withPositionRank: true);
```

Worth documenting beyond the happy path: the tier behaviour (free tier truncates and sets `ApiLimits::$limited`, so `truncated()` is worth checking), and the two API quirks the examples uncovered — QB/K/DST ranks come back under `STD` with `PPR`/`HALF` empty, and narrowing the experts route by `rankingType` returns an empty directory for a season whose rankings do not exist yet.

`CONTRIBUTING.md` already covers the gate, fixture policy and release process, so this is user-facing documentation only — do not duplicate it.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Every implemented endpoint has a copy-pasteable example
- [ ] #2 All snippets use the connector's endpoint methods rather than constructing Requests or calling ->dto()
- [ ] #3 Install and API-key configuration are documented, including that the key is read from FANTASYPROS_API_KEY
- [ ] #4 Snippets that duplicate an examples/ file agree with it
- [ ] #5 Tier truncation and the documented API quirks are explained where a consumer would otherwise be surprised
<!-- AC:END -->

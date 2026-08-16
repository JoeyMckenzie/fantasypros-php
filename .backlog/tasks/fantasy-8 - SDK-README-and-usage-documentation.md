---
id: FANTASY-8
title: SDK README and usage documentation
status: Done
assignee:
  - '@claude'
created_date: '2026-08-14 06:16'
updated_date: '2026-08-16 00:09'
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
modified_files:
  - README.md
  - src/
  - tests/
  - examples/
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
- [x] #1 Every implemented endpoint has a copy-pasteable example
- [x] #2 All snippets use the connector's endpoint methods rather than constructing Requests or calling ->dto()
- [x] #3 Install and API-key configuration are documented, including that the key is read from FANTASYPROS_API_KEY
- [x] #4 Snippets that duplicate an examples/ file agree with it
- [x] #5 Tier truncation and the documented API quirks are explained where a consumer would otherwise be surprised
<!-- AC:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## The README

Nine endpoint sections, one per route, each a trimmed version of the matching
`examples/` script so the two agree. Table of contents, install, API key, failure
handling, truncation, and the three quirks. No duplication of CONTRIBUTING.

Snippets were verified rather than eyeballed: all of them were pasted into one scratch
file and run through PHPStan against the real SDK. That caught nothing in the end, but it
is the only way to be sure a public README does not name a property that does not exist.
Worth repeating whenever the README changes.

## The comment sweep

The maintainer asked for the em dashes to go across the whole codebase, and for the
comments to read less like an LLM wrote them. 101 instances of ` -- ` across 49 files,
plus the surrounding tics.

Voice was taken from the maintainer's own writing rather than guessed at: code comments in
the sibling `website` repo (terse, factual, colons and semicolons where an em dash would
go, e.g. "Deleted mid-review; nothing to persist.") and a recent blog post for register
(contractions, parentheses for asides, second person). The blog itself uses em dashes
freely, but the instruction was explicit, so the instruction won.

What changed:

- 18 route summaries went from `GET /path -- description` to `GET /path: description`,
  scripted since the pattern was uniform.
- The remaining ~80 were rewritten by hand, choosing a colon, semicolon, parentheses or a
  full stop depending on what the clause was actually doing.
- Bolded pull-quote openers in docblocks (`**The spec's per-position schemas are
  wrong.**`) lost their bold and their drama.
- "deliberately", "on purpose", "load-bearing" and similar ceremony went, keeping the
  reasoning underneath.
- `printf` separators in the examples changed too. They are output rather than comments,
  but leaving ` -- ` in the text a reader sees while stripping it from the comments would
  have been inconsistent.

Substance was preserved everywhere. The hard-won notes (the ROS `players: null`, the DST
stat keys, the Deptrac cycle reasoning) all still say the same thing, just without the
punctuation tell.

Gate re-run after the sweep: Pint, Rector, PHPStan level max, Deptrac, 238 tests.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
README covers install, API key setup, all nine endpoints, failure handling, tier truncation and the API quirks, with a table of contents. Every snippet is a trimmed version of its `examples/` script, so a reader who copies from the README and one who runs the example see the same call.

**The snippets are type-checked, not eyeballed.** All of them were pasted into one scratch file and run through PHPStan against the real SDK: no wrong property names, no methods that don't exist. Worth doing again whenever the README changes, since a broken snippet in public docs is the most embarrassing kind of bug.

**The comment sweep covered 101 em dashes across 49 files.** Voice came from your own writing rather than guesswork: the code comments in your `website` repo (terse, colons and semicolons where an em dash would go) and a recent blog post for register. Your blog does use em dashes, but you asked for them gone, so they're gone.

Beyond the dashes: bolded pull-quote openers in docblocks lost the bold, and the "deliberately / on purpose / load-bearing" ceremony went while the reasoning underneath stayed. The `printf` separators in the examples changed too, since leaving them in output a reader sees while stripping them from comments would have been half a job.

Nothing lost in substance. The ROS `players: null` trap, the DST stat keys, the Deptrac cycle reasoning all still say what they said.

Gate green after the sweep: Pint, Rector, PHPStan level max, Deptrac, 238 tests.
<!-- SECTION:FINAL_SUMMARY:END -->

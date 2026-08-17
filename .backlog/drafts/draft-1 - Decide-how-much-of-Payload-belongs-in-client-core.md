---
id: DRAFT-1
title: Decide how much of Payload belongs in client-core
status: Draft
assignee: []
created_date: '2026-08-17 22:10'
updated_date: '2026-08-17 22:10'
labels:
  - php
  - shared-package
  - decision
  - deferred
dependencies:
  - FANTASY-18.2
documentation:
  - 'https://docs.saloon.dev/llms.txt'
parent_task_id: FANTASY-18
priority: low
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Deliberately deferred until the Sleeper client exists. Held as a draft because the decision cannot be made well from two data points, and committing to it now risks the wrong abstraction.

Payload is the file that looks most duplicated across fantasypros-php and myfantasyleague-php and is the most dangerous to share. The common core is small — construction from an array, construction from a Response, and the has/string/nullableString/int/nullableInt readers, roughly 60 lines. Everything else is API-specific:

- FantasyPros (327 lines, 16 readers) adds nullableFloat, bool, list readers for strings and ints, and five *Map readers that exist because some payloads key objects by ID.
- MyFantasyLeague (191 lines, 10 readers) adds id() (MFL identifiers have significant leading zeros, so coercion would corrupt them), timestamp(), text() and collection() — all artifacts of MFL's JSON being a mechanical Badgerfish transform of XML, where every scalar arrives quoted and a single-element list collapses to a bare object.

Sharing the whole class would make it the union of every API's quirks. Sleeper is JSON-native REST with real integers and booleans, no envelope and no credential, so it needs almost none of the FantasyPros numeric-string coercion and none of the MyFantasyLeague Badgerfish handling — but under a shared class it would inherit both.

The proposal to evaluate once Sleeper is written: ship only the universal readers from client-core as a trait, and let each client keep its own final Payload that uses the trait and adds the readers its API actually needs. A trait rather than a shared base class specifically because Pint's final_class rule is enforced in these repos, and because it matches the trait-based Concerns pattern the connectors already use. This should be re-examined against what Sleeper actually needs rather than adopted on faith.

Promote this out of draft once the Sleeper client has real endpoints and its Payload requirements are known.

Repos: /Users/joeymckenzie/code/github.com/joeymckenzie/fantasy and ../myfantasyleague.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 The Payload needs of all three clients, including Sleeper, are compared and the genuinely universal readers are identified from evidence rather than assumption
- [ ] #2 A decision is recorded on whether to share Payload at all, and if so via which mechanism, with the reasoning captured
- [ ] #3 If shared: each client's Payload remains final and keeps the readers specific to its own API, with no API-specific reader promoted into client-core
- [ ] #4 If shared: MyFantasyLeague's leading-zero identifier handling and single-element collection collapse still behave identically, covered by existing tests
- [ ] #5 If shared: FantasyPros' numeric-string coercion for ranks and seasons still behaves identically, covered by existing tests
- [ ] #6 If not shared: the reasoning is documented so the question is not silently reopened for a fourth client
- [ ] #7 All clients' suites, mutation testing and Deptrac pass
<!-- AC:END -->

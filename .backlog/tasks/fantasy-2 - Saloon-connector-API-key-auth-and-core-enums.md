---
id: FANTASY-2
title: 'Saloon connector, API-key auth, and core enums'
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - sdk
dependencies:
  - FANTASY-1
priority: high
ordinal: 2000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
FantasyProsConnector with base URL https://api.fantasypros.com/public/v2/json, x-api-key header auth (key via constructor, resolved from FANTASYPROS_API_KEY env in a small factory), JSON defaults, timeout + retry config. Enums under Sdk/Enums: Sport, Position, Scoring, RankingType, NewsCategory.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Connector sends x-api-key header (asserted via Saloon MockClient test)
- [ ] #2 Enums cover the values enumerated in docs/fantasypros-open-api-spec-v2.yml
- [ ] #3 Missing API key fails with a clear exception, not a 401 at request time
<!-- AC:END -->

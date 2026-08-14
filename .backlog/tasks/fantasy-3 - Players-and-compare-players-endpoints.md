---
id: FANTASY-3
title: Players and compare-players endpoints
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - sdk
dependencies:
  - FANTASY-2
priority: medium
ordinal: 3000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /{sport}/players (player id, updated-since, ecr, external_ids, pos_rank params) and GET /{sport}/compare-players (players, position, ranking_type, details). Readonly DTOs for NFL player + comparison results under Sdk/Data via createDtoFromResponse.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Requests build correct paths and query strings (MockClient tests)
- [ ] #2 NFL fixture responses hydrate into DTOs
<!-- AC:END -->

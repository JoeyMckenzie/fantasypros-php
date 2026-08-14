---
id: FANTASY-4
title: News and injuries endpoints
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - sdk
dependencies:
  - FANTASY-2
priority: medium
ordinal: 4000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /{sport}/news (fpid, limit, category, order_by) and GET /{sport}/injuries (year, week, include_probabilities, team_id/player_ids colon lists). DTOs for news items and NFL injury entries.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Colon-delimited list params serialize correctly from PHP arrays
- [ ] #2 NFL fixtures hydrate into DTOs with tests passing
<!-- AC:END -->

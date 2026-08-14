---
id: FANTASY-5
title: 'Rankings, consensus rankings, and experts endpoints'
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - sdk
dependencies:
  - FANTASY-2
priority: medium
ordinal: 5000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /{sport}/{season}/rankings, GET /{sport}/{season}/consensus-rankings (position, scoring, week, experts=show), GET /{sport}/{season}/rankings/experts. DTOs for ranked players (ECR, min/max ranks) and expert profiles.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Season/week path+query combinations covered by tests
- [ ] #2 Consensus rankings hydrate DTOs incl. rank ranges when range=true
<!-- AC:END -->

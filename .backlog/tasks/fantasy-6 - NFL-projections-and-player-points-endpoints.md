---
id: FANTASY-6
title: NFL projections and player-points endpoints
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - sdk
dependencies:
  - FANTASY-2
priority: medium
ordinal: 6000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
GET /nfl/{season}/projections (position, week, ros, players list) and GET /nfl/{season}/player-points (start/end week, position, scoring). Projection stats vary by position (QB vs RB/WR/TE vs DST) — model as per-position stat DTOs or a stats map, decide in-ticket.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Weekly and ROS projections requests covered by fixture tests
- [ ] #2 Player points weekly breakdown hydrates correctly
<!-- AC:END -->

---
id: FANTASY-10
title: MCP tools wrapping SDK endpoints
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - mcp
dependencies:
  - FANTASY-9
priority: medium
ordinal: 10000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Tools consuming the SDK: get_rankings, get_projections, get_injuries, get_news, compare_players, get_player_points. Tool schemas use the SDK enums; responses trimmed to what lineup analysis needs (token-friendly).
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Each tool callable end-to-end from Claude with a real API key
- [ ] #2 Tool inputs validate sport/position/scoring against enums
<!-- AC:END -->

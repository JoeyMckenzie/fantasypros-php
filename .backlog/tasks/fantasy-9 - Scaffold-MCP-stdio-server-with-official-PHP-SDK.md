---
id: FANTASY-9
title: Scaffold MCP stdio server with official PHP SDK
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - mcp
dependencies:
  - FANTASY-2
priority: medium
ordinal: 9000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Require mcp/sdk (modelcontextprotocol/php-sdk). Stdio server entrypoint at bin/fantasy-mcp under Fantasy\Mcp, wiring the FantasyProsConnector from FANTASYPROS_API_KEY. Register one hello-world tool to prove the transport.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Server responds to MCP initialize + tools/list over stdio
- [ ] #2 Documented claude mcp add / .mcp.json snippet works locally
<!-- AC:END -->

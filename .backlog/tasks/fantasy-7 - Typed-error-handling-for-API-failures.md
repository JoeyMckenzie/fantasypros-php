---
id: FANTASY-7
title: Typed error handling for API failures
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - sdk
dependencies:
  - FANTASY-2
priority: medium
ordinal: 7000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Map API failures to typed exceptions: 401/403 auth, 400 validation (spec returns {message, parameter, valid_format}), 429 rate limit. Wire Saloon's shouldThrowRequestException / custom RequestException resolution on the connector.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Each failure class has a MockClient test asserting the thrown exception type and message content
<!-- AC:END -->

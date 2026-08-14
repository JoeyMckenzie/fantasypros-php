---
id: FANTASY-1
title: Scaffold Composer package and dev tooling
status: To Do
assignee: []
created_date: '2026-08-14 06:16'
labels:
  - sdk
dependencies: []
priority: high
ordinal: 1000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Single Composer package (PSR-4: Fantasy\ => src/). Require saloonphp/saloon v3; dev deps: pestphp/pest, phpstan/phpstan, laravel/pint. Add composer scripts (test, lint, analyse). Delete vestigial apps/ and packages/ dirs. See CONTEXT.md for the layout decision.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 composer install completes cleanly on PHP 8.3+
- [ ] #2 composer test, composer lint, composer analyse all run
- [ ] #3 Fantasy\Sdk and Fantasy\Mcp classes autoload via PSR-4
<!-- AC:END -->

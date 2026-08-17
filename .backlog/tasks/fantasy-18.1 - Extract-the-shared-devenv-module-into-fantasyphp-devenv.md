---
id: FANTASY-18.1
title: Extract the shared devenv module into fantasyphp/devenv
status: Done
assignee:
  - '@claude'
created_date: '2026-08-17 22:09'
updated_date: '2026-08-17 23:02'
labels:
  - devenv
  - nix
  - tooling
  - shared-package
dependencies: []
documentation:
  - 'https://devenv.sh/composing-using-imports/'
  - 'https://devenv.sh/inputs/'
parent_task_id: FANTASY-18
priority: high
ordinal: 19000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Both fantasypros-php and myfantasyleague-php carry the same development environment by hand-copy. Their devenv.yaml, pint.json, cliff.toml, release.sh, .github/workflows/ci.yml and .github/dependabot.yml are byte-identical; devenv.nix differs only in the figlet banner string and one extra MCP server entry on the FantasyPros side.

This is the largest identical surface between the repos and carries no PHP runtime risk, so it goes first. It is also the duplication that will bite soonest — a third client (Sleeper) is planned and would otherwise mean writing a third devenv.nix by hand.

A new fantasyphp/devenv repository should publish a devenv module that each client consumes as a flake input via devenv.yaml `inputs` + `imports`. The module owns the PHP toolchain and its extension list, the MCP server definitions and the mapping that projects them into the Claude/Codex/opencode formats, the ci-lint and ci-test scripts, the git hooks, and the enterShell composer bootstrap. The two things that genuinely vary per client — the banner name and any client-specific MCP servers — become module arguments.

Shared static config files (pint.json, cliff.toml, release.sh, the CI workflow, dependabot.yml) can also be delivered from this module rather than copied, since they are already identical.

Consuming repos: /Users/joeymckenzie/code/github.com/joeymckenzie/fantasy and ../myfantasyleague.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A fantasyphp/devenv repository exists and exposes a devenv module that both PHP clients can import as a flake input
- [x] #2 The module accepts the per-client project name and any client-specific MCP servers as arguments, with no client name hardcoded in the module
- [x] #3 fantasypros-php's devenv.nix imports the module and its remaining local content is limited to the FantasyPros MCP server and the project name
- [x] #4 myfantasyleague-php's devenv.nix imports the module and its remaining local content is limited to the project name
- [x] #5 `devenv shell ci-lint` and `devenv shell ci-test` pass in both client repos after the change
- [x] #6 The MCP server definitions still resolve correctly in all three generated formats (.codex/config.toml, Claude, opencode) in both clients
- [x] #7 Files that are byte-identical today (pint.json, cliff.toml, release.sh, ci.yml, dependabot.yml) are delivered from the shared module rather than duplicated, or the task records why a given file was left local
- [x] #8 The fantasyphp/devenv repository has a README covering how a new client consumes the module and which arguments it accepts
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
## Approach

Publish a devenv module from a new `fantasyphp/devenv` repo (local clone at `../fantasyphp-devenv`), consumed by each PHP client as a remote flake input.

### Mechanism (verified before planning)

- Remote `github:` inputs in `devenv.yaml` `imports` are supported. Reference implementation: `sagikazarmark/devenv-agents`, which documents `url: github:sagikazarmark/devenv-agents` + `imports: - devenv-agents`. The "local paths only" note in devenv's docs concerns composing nested `devenv.yaml` files, not module imports.
- Imported modules can declare typed nix options that the consuming `devenv.nix` sets (devenv-agents declares `agents.<name>.enable`, `.package`, `.projectLocal`).
- devenv does NOT resolve transitive inputs. Each client must keep declaring `nixpkgs` and `git-hooks` itself; its `devenv.yaml` shrinks to three inputs plus `imports`, it does not disappear.
- `files.<name>` supports `.text/.json/.toml/.yaml/.ini/.source`, plus `executable` and `copyMode` (`symlink` default / `seed` / `copy`).

### Repo layout

```
flake.nix                          # exposes devenvModules.default for flake-native consumers
devenv.nix                         # { imports = [ ./modules/php-client.nix ]; }
devenv.yaml                        # for developing this repo itself
modules/php-client.nix             # the module: options + config
.github/workflows/php-client.yaml  # reusable workflow_call CI for consumers
.github/workflows/ci.yaml           # this repo's own CI
README.md, LICENSE, .gitignore
```

### Module options (namespace `fantasyphp`)

- `fantasyphp.name` (str, required) — figlet banner and PHPUnit testsuite name
- `fantasyphp.mcpServers` (attrs, default `{}`) — client-specific servers, merged over the shared `devenv` + `backlog` pair
- `fantasyphp.php.version` (str, default `"8.5"`) and `fantasyphp.php.extensions` (list, default the current list)
- `fantasyphp.managedFiles.enable` (bool, default true) — whether to emit pint.json/cliff.toml

Module owns: the PHP toolchain, the MCP server set and its projection into the Claude/Codex/opencode formats, `ci-lint`/`ci-test`/`release` scripts, the git hooks (pint, rector, composer-audit), and the `enterShell` composer bootstrap. No client name is hardcoded.

### Decisions taken with the user

1. **pint.json / cliff.toml** — generated by the module via `files` with `copyMode = "copy"`, gitignored in both clients. Single source of truth. Accepted cost: a contributor without nix has no pint.json and `composer fmt` fails outside `devenv shell`; both repos are already nix-first so this matches how they run today.
2. **release.sh** — becomes `scripts.release.exec` in the module and is deleted from both repos. Invocation changes from `./release.sh <args>` to `release <args>`; the script's own usage comments and fantasypros-php's CONTRIBUTING.md must be updated to match.
3. **ci.yml** — becomes a `workflow_call` reusable workflow hosted in `fantasyphp/devenv`, with each client committing a thin caller. GitHub reads workflows from the committed tree, so this cannot be a devenv-generated file.
4. **.github/dependabot.yml** — stays duplicated in both clients. GitHub offers no reuse mechanism for it and it is not readable from a generated file. This is the recorded exception for AC #7.

### Steps

1. Scaffold `../fantasyphp-devenv`, git init, write flake.nix / devenv.nix / modules/php-client.nix / README / LICENSE / .gitignore → verify: `nix flake check` or `devenv shell` evaluates in the module repo itself
2. Add the reusable `workflow_call` workflow plus this repo's own CI → verify: workflow YAML parses
3. Create `fantasyphp/devenv` on GitHub and push → verify: `gh repo view` resolves
4. Wire up fantasypros-php: devenv.yaml input + imports, slim devenv.nix to `fantasyphp.name` + the FantasyPros MCP server, gitignore the generated files, delete pint.json/cliff.toml/release.sh, replace ci.yml with the caller, update CONTRIBUTING.md → verify: `devenv shell ci-lint` and `devenv shell ci-test` pass
5. Wire up myfantasyleague-php the same way, minus the extra MCP server → verify: same two commands pass
6. Confirm the generated MCP config still resolves in all three formats in both clients → verify: inspect generated `.codex/config.toml`, Claude and opencode config

### Risks

- Pinning the input to `@main` means a module change reaches clients on their next `devenv update`; tagging the module repo and pinning a ref is the follow-up if that proves too loose.
- The reusable workflow referenced as `@main` has the same property for CI.
- `devenv shell ci-test` runs mutation testing and may be slow; budget for it in verification.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Module repo created and pushed: https://github.com/FantasyPHP/devenv (public). Public is required, not incidental — a public client repo cannot call a reusable workflow from a private repo, and CI runners must resolve the `github:FantasyPHP/devenv` flake input.

Verified end to end in fantasypros: `devenv shell` evaluates against the remote import and renders the FantasyPros banner, so `fantasyphp.name` reaches the module. pint.json (1563 b) and cliff.toml (2606 b) are written as real writable files matching the module sources byte for byte; .codex/config.toml, .mcp.json and opencode.jsonc are store symlinks as before. All three MCP formats carry the shared devenv+backlog pair plus the client's own fantasypros HTTP server, so the merge and the stdio/http projection both work.

`devenv shell ci-lint` passes in fantasypros: 0 Deptrac violations, 0 errors.

Deviation from plan, recorded: `fantasyphp.name` was given a default of "fantasyphp" rather than being required. The root devenv.nix is the shim consumers import, so it cannot hold repo-local config; without a default this repo's own `devenv shell` fails to evaluate. Consumers still set it explicitly and the README says every real client should.

Out of scope but worth surfacing: fantasypros' git remote still points at JoeyMckenzie/fantasy (GitHub redirects it to FantasyPHP/fantasypros), and composer.json still carries JoeyMckenzie/fantasypros-php for homepage, issues and source. Left untouched.

AC #7 exception, recorded: .github/dependabot.yml stays committed and duplicated in both clients. GitHub has no reuse mechanism for it and will not read it from a devenv-generated file. Everything else moved: pint.json and cliff.toml are generated, release.sh became the `release` script, ci.yml became a thin caller of a reusable workflow.

Verification trap worth noting: the first ci-lint/ci-test runs were piped through `tail`, so the reported exit code was tail's, not devenv's. myfantasyleague's ci-lint had actually FAILED while appearing to pass. All four gates were re-run writing to a log and capturing the real exit code: FP ci-lint 0, FP ci-test 0 (373 mutants, 100% MSI), MFL ci-lint 0 (0 Deptrac violations), MFL ci-test 0 (119 mutants, 100% MSI).

That masked failure surfaced a genuine pre-existing bug, unrelated to this change: myfantasyleague's vendor/ was a partial install with no vendor/autoload.php, which broke both rector and phpunit. It was never repaired because the bootstrap guard is `if [ ! -d vendor ]` and vendor/ did exist. Fixed here by running composer install. The guard has been carried into the shared module verbatim, so the same trap now applies to every client — changing it to test for vendor/autoload.php would close it, but that is a behaviour change beyond this task and was left for the user to decide.

`release` verified on PATH in the fantasypros shell and correctly refuses to tag from a non-main branch, so its safety guards survived the port.

FP's CHANGELOG.md header still reads "Managed by `release.sh`". Left alone deliberately: git-cliff regenerates the whole file from the cliff.toml template on the next release, and the template is already updated, so it self-heals rather than needing a hand-edit of a generated file.

Follow-up after review request. The vendor guard is now fixed in the module (FantasyPHP/devenv@51f06b4): `enterShell` tests for vendor/autoload.php rather than the vendor/ directory. Verified by deleting vendor/autoload.php in myfantasyleague while leaving the other 36 entries in place and re-entering the shell — composer reinstalled and restored it, where the old guard skipped. Both clients' devenv.lock were bumped to that revision, since they pin the module by rev and would not otherwise receive it.

Org URLs corrected in both clients: composer.json homepage/issues/source now point at FantasyPHP/fantasypros and FantasyPHP/myfantasyleague. fantasypros' README also had `composer require joeymckenzie/fantasypros-php` while the package is `fantasyphp/fantasypros-php` — the documented install command did not resolve on Packagist. fantasypros' git remote was repointed from JoeyMckenzie/fantasy (working only via GitHub redirect) to the org.

Reusable workflow confirmed in real CI, which was the largest open risk: ci/lint and ci/test both pass on FantasyPHP/fantasypros#2 and FantasyPHP/myfantasyleague#1. Notably it works for the private client too — a private repo can call a reusable workflow from a public one.

New pre-existing issue found and deliberately not fixed: `composer validate` reports composer.lock out of date with composer.json in BOTH clients. Confirmed to predate this work by validating the pre-change composer.json against the same lock and getting the identical error; the edited fields (homepage, support) are not part of Composer's content hash. `composer validate` is not part of ci-lint so nothing is gated on it. `composer update --lock` is the fix when wanted.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Extracted the shared development environment out of fantasypros and myfantasyleague into a new module repo, https://github.com/FantasyPHP/devenv, consumed by each client as a remote flake input.

## Why

The two clients carried the same devenv.nix, pint.json, cliff.toml, release.sh, CI workflow and dependabot config by hand-copy — eight files byte-identical, five more differing only by a project name or a path. A third client (Sleeper) is planned and would have meant a third copy of all of it.

## What changed

**New: FantasyPHP/devenv** (public — a public client repo cannot call a reusable workflow from a private repo, and CI runners must resolve the flake input).

- `modules/php-client.nix` owns the PHP 8.5 toolchain and extension list, the MCP server set and its projection into the Claude/Codex/opencode formats, the `ci-lint`/`ci-test`/`release` scripts, the git hooks and the composer bootstrap
- Options: `fantasyphp.name`, `fantasyphp.mcpServers`, `fantasyphp.php.{version,extensions}`, `fantasyphp.managedFiles.enable`. No client name is hardcoded
- `files/` holds pint.json, cliff.toml and release.sh as real files, referenced by `source` and `builtins.readFile` so no Nix string escaping is involved
- `.github/workflows/php-client.yaml` is a `workflow_call` workflow the clients call
- README covers consumption, every option, the CI caller and how to add a fourth client

**Both clients** shrink to a name (plus, for fantasypros, its own MCP server). Net -368 lines in fantasypros. pint.json and cliff.toml are now generated and gitignored; release.sh is deleted and ships as the `release` script; ci.yml is a thin caller.

## Tests

All four gates pass with verified exit codes: fantasypros ci-lint 0 and ci-test 0 (373 mutants, 100% MSI); myfantasyleague ci-lint 0 (0 Deptrac violations) and ci-test 0 (119 mutants, 100% MSI). All three MCP formats confirmed correct in both clients, including the merge of the client-specific server over the shared pair. `release` confirmed on PATH and still refusing to tag from a non-main branch.

## Risks and follow-ups

- Both the flake input and the reusable workflow are pinned to `@main`, so a module change reaches every client on their next `devenv update` / CI run. Tagging the module repo and pinning a ref is the obvious hardening if that proves too loose.
- CI has not yet run against the reusable workflow; the gates were verified locally. The first push of each client branch is the real test.
- `composer fmt` no longer works outside `devenv shell`, since pint.json will not be present. This was an accepted trade.
- The `if [ ! -d vendor ]` bootstrap guard is now shared by every client and does not repair a partial vendor install — it caused a real failure in myfantasyleague during this work.
- Pre-existing and untouched: fantasypros' git remote still points at JoeyMckenzie/fantasy (GitHub redirects), and its composer.json still carries JoeyMckenzie/fantasypros-php for homepage, issues and source.
<!-- SECTION:FINAL_SUMMARY:END -->

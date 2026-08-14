# Contributing

## Getting set up

The toolchain is defined by [devenv](https://devenv.sh), so the shell gives you the right
PHP and extensions without installing anything globally:

```bash
devenv shell     # installs composer dependencies on first entry
composer test    # fully offline, no API key needed
```

## The quality gate

Everything below must pass before a change lands. Run them in this order — Pint and Rector
disagree about formatting if you run them the other way around.

```bash
composer fmt          # Laravel Pint
composer refactor     # Rector
composer lint         # PHPStan level max, 100% type coverage
composer test         # PHPUnit, offline
./vendor/bin/deptrac analyse --config-file=deptrac.php
composer test:mutate  # Infection, minMsi 95
```

`composer fmt:check` and `composer refactor:check` are the non-mutating variants.

**Deptrac enforces the internal layering**, outermost first: `connector` may reach
anything; `requests` may reach `data`, `enums` and `exceptions`; `data` may reach `enums`
and `exceptions`; `enums` and `exceptions` are leaves. A DTO reaching back up into the
request that returns it is a violation, and so is anything depending on the connector.

**Infection escapees get a real test, not an ignore.** The exceptions in `infection.json5`
are all mutants that are provably unobservable — casts that exist only to satisfy PHPStan,
or an `mb_*` call on ASCII input — and each carries a comment saying why. If you add one,
prove the mutant is equivalent first.

## Tests and fixtures

The suite runs in two modes, selected by `FANTASY_FIXTURES`:

- **`composer test`** (default) — fully offline. Fixtures under `tests/Fixtures/Saloon/` are
  replayed, and `MockConfig::throwOnMissingFixtures()` turns a missing fixture into a hard
  failure. The suite can never quietly reach the live API, which is why CI needs no API key.
- **`composer test:record`** — maintainer-only. Loads `.env`, requires a real
  `FANTASYPROS_API_KEY`, and records any missing fixture from the live API.
  `composer fixtures:refresh` wipes and re-records everything.

Fixtures are committed on purpose: the offline suite exercises real payload shapes rather
than guesses at the OpenAPI spec.

**Record a fixture and read it before modelling any DTO.** The spec in `docs/` has diverged
from the live API in a dozen documented ways — fields it marks required arriving null, keys
named differently from the schema, enum values outside the declared set, and at least one
parameter whose description contradicts its own pattern. Modelling from the spec has
produced DTOs that throw on genuine responses. Keep new fixtures small; narrow the request
rather than recording an unfiltered endpoint.

## Releasing

Versions follow [SemVer](https://semver.org) and are published to Packagist from git tags.

1. Make sure the full gate above is green.
2. Tag the release: `git tag -a v1.2.3 -m "v1.2.3" && git push origin v1.2.3`.
3. Packagist picks the tag up through the GitHub webhook.

Because this is a library, the public surface is the contract: the connector, the request
classes and their constructor signatures, the DTOs and their properties, and the enum
cases. Anything that changes those in an incompatible way is a major bump. Adding an
endpoint, a request parameter with a default, or a new DTO property is a minor bump.

The supported PHP range lives in `composer.json` (`require.php`). Raising it is a major
bump; it must move together with `rector.php`'s target version and whatever CI verifies.

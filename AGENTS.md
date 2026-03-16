# AGENTS.md

## Overview

- `sentry/sentry` is the core PHP SDK, not an application or framework bundle.
- Public entry points include the autoloaded global helpers in
  `src/functions.php` and the public classes and interfaces under `src/`.
- Use this file for repo-specific constraints that are easy to miss, and
  explore the codebase for current implementation details.

## Compatibility Rules

- The minimum supported PHP version for shipped code is `7.2`.
  `composer.json` requires `^7.2|^8.0`, so shipped code must remain valid on
  PHP `7.2` unless support policy is intentionally being changed.
- This SDK has a broad public API surface. Treat changes to global helpers,
  `Options`, `ClientBuilder`, `ClientInterface`, `HubInterface`,
  `TransportInterface`, `IntegrationInterface`, tracing/logs/metrics types, and
  Monolog handlers as BC-sensitive.
- Preserve the existing cross-version compatibility style. This repo supports
  multiple `psr/log`, `symfony/options-resolver`, `guzzlehttp/psr7`, and
  `monolog/monolog` major versions.
- Do not assume optional packages or binaries are available. Monolog is only a
  suggested dependency, and FrankenPHP/RoadRunner worker coverage depends on
  optional binaries and dev dependencies.
- `Spotlight` is treated as an active send path alongside DSN-based delivery.
  Do not gate runtime setup or transport behavior on DSN alone.

## Editing Guidance

- Keep `declare(strict_types=1);` in PHP files.
- Follow the existing formatting rules from `.php-cs-fixer.dist.php`.
- If you add or change an SDK option, update the resolver/defaults in
  `Options`, the relevant getters and setters, the `init()` option array-shape
  docs in `src/functions.php`, and the affected tests.
- `src/functions.php` is autoloaded and part of the public API. Keep helper
  signatures, phpdoc, and runtime behavior synchronized with the underlying
  client, hub, and runtime-context implementation. Functions in
  `src/functions.php` should use camelCase naming.
- `IntegrationRegistry` intentionally calls `setupOnce()` only once per
  integration class during the process lifetime. Preserve de-duplication and
  default-integration gating when changing integration setup behavior.
- `ErrorHandler` has fragile register-once, previous-handler chaining, reserved
  memory, and out-of-memory behavior. Preserve that lifecycle carefully and add
  PHPT coverage when changing fatal or silenced error handling.
- `SentrySdk::startContext()`, `endContext()`, and `withContext()` must keep
  runtime-context isolation and best-effort flushing intact for logs, metrics,
  and transport in long-running worker scenarios.
- `HttpTransport` and `PayloadSerializer` are tightly coupled. Preserve the
  envelope item selection, Spotlight delivery path, dynamic sampling context,
  and the transaction/profile relationship when changing transport or
  serialization behavior.
- Monolog support spans multiple Monolog major versions through the
  compatibility traits and handlers under `src/Monolog/`. Preserve that
  compatibility style when changing logging integrations.
- `Client::SDK_VERSION` is updated by the release action via
  `scripts/bump-version.sh`. Do not modify it manually as part of normal
  development changes.

## Test Expectations

- Add tests with every behavior change. This is a library repo with broad
  compatibility and regression coverage.
- New tests belong under `tests/`.
- `phpunit.xml.dist` defines a `unit` suite that includes both PHPUnit tests
  and `tests/phpt`, plus a separate `oom` suite for `tests/phpt-oom`.
- Prefer targeted PHPUnit runs while iterating.
- After editing files, run the relevant formatting, lint, and test commands for
  the code you changed.
- Before handing back substantive code changes, run `composer check` when
  feasible and call out anything you could not run.
- If you change error handling, fatal error capture, or PHP-version-specific
  behavior, add or update PHPT coverage.
- If you change runtime-context or worker-mode behavior, add or update focused
  coverage for the FrankenPHP or RoadRunner paths as appropriate.

## Tools And Commands

- `phpstan.neon` only analyzes `src`, uses `phpstan-baseline.neon`, and will
  not catch behavior regressions in `tests/` or PHPT coverage.
- `phpunit.xml.dist` is strict about unexpected output, so noisy debug output
  will fail tests.
- This repo is a library, so do not expect a runnable application entrypoint.

## Docs And Release Notes

- `README.md` and `CHANGELOG.md` are updated manually during releases, so do
  not modify them as part of normal development changes.
- If a change may require updates in the separate documentation repo, ask the
  user whether to review `../sentry-docs` if that sibling checkout exists. If
  it does not exist, ask the user for the local docs path first. If they opt
  in, update that repo's `master` branch when safe, use git worktrees to
  inspect the relevant docs, and suggest any needed changes to avoid stale
  documentation.
- If a change affects installation, configuration, error handling, tracing,
  profiling, metrics, logs, or worker-mode behavior, call out the likely
  README or release-note follow-up in your summary instead of editing those
  files automatically.

## CI Notes

- `.github/workflows/ci.yml` runs the PHPUnit compatibility matrix across
  Ubuntu and Windows, lowest and highest dependencies, and separate runtime
  jobs for FrankenPHP and RoadRunner.
- `.github/workflows/static-analysis.yaml` runs PHP-CS-Fixer, PHPStan, and
  Psalm on single recent PHP versions rather than across the full test matrix.

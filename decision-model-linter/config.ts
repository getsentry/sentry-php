// sentry-php's decision-model lint rules: https://github.com/getsentry/decision-model-linter
// Each rule is a former Bugbot learned rule (getsentry/bugbot-rules, repo/sentry-php) that fits in
// one file and has no deterministic check yet. Move a rule to PHPStan, a test, or a design change
// when one can enforce it, and delete it here.
import type { Config, Model, Suite } from "@sentry/decision-model-linter";

const jev = {
  provider: "openrouter",
  name: "typesafe/jev-1.13",
  apiKey: process.env.OPENROUTER_API_KEY,
  maxInputTokens: 32_768,
} satisfies Model;

const sdk = {
  files: ["src/**/*.php"],
  rules: {
    // Bugbot rule 50. Delete once Options hands out callbacks that are already wrapped.
    unguardedUserCallback: {
      prompt:
        "Does the code call a user-provided option callback (before_send, before_send_transaction, before_send_check_in, before_send_log, before_send_metric, before_breadcrumb, traces_sampler, profiles_sampler, profiler_factory, or a callable returned by another Options getter) outside a try/catch that catches \\Throwable?",
      values: [
        {
          value: "TRUE",
          description:
            "A callback obtained from Options, such as getBeforeSendCallback(), getTracesSampler(), or getProfilerFactory(), is invoked and an exception it throws would reach the host application.",
          severity: "warn",
          report:
            "A user callback that throws must not crash the host app. Call it inside `try { ... } catch (\\Throwable $e)`, log the failure through the SDK logger, and fall back: drop the item for `before_send*`, use `traces_sample_rate` for `traces_sampler`, use `profiles_sample_rate` for `profiles_sampler`, and continue without a profile for `profiler_factory`.",
        },
        {
          value: "FALSE",
          description:
            "Every user callback is invoked inside a try/catch that catches \\Throwable, or the code invokes no user callback. Validating, storing, or returning a callback without calling it does not count.",
        },
      ],
    },
    // Bugbot rule 37.
    ungatedAutomaticPii: {
      prompt:
        "Does the code automatically collect personal data, such as the user's IP address, cookies, request headers or bodies, or the scope's user attached onto logs, metrics, or other telemetry, without checking send_default_pii (Options::shouldSendDefaultPii()) or going through DataCollectionPolicy?",
      values: [
        {
          value: "TRUE",
          description:
            "New collection of request or user data happens on every call, with no send_default_pii or DataCollectionPolicy check deciding whether it runs.",
          severity: "error",
          report:
            "Automatic collection of personal data must be off unless `send_default_pii` is on, or must go through `DataCollectionPolicy` when `data_collection` is set. Gate this collection on one of them.",
        },
        {
          value: "FALSE",
          description:
            "Automatic collection is gated on send_default_pii or DataCollectionPolicy, or the code collects no personal data. Serializing user data that the application set explicitly, with setUser(), configureScope(), or explicit log or metric attributes, does not count: send_default_pii only gates automatic collection.",
        },
      ],
    },
    // Bugbot rule 48.
    ignoresGivenHub: {
      prompt:
        "Does a function that receives a HubInterface, ClientInterface, or Scope argument also call SentrySdk::getCurrentHub(), SentrySdk::getClient(), or SentrySdk::getIsolationScope() for a lookup that the argument should answer?",
      values: [
        {
          value: "TRUE",
          description:
            "Within one function, the given hub, client, or scope is used for some lookups while others re-resolve the global one from SentrySdk.",
          severity: "warn",
          report:
            "Resolve the hub, client, or scope once, for example `$hub = $hub ?? SentrySdk::getCurrentHub();`, and use that instance for every lookup in this function. Pass it on to helpers that read current state, such as `getTraceparent()` and `getBaggage()`, so outgoing headers match the hub the caller gave.",
        },
        {
          value: "FALSE",
          description:
            "Each function that takes a hub, client, or scope uses only that instance, apart from one fallback when the argument is null; or no function takes one.",
        },
      ],
    },
    // Bugbot rule 19. Delete once LogLevel::__toString() is removed.
    sentryLevelToPsrLogger: {
      files: ["src/Logs/**/*.php"],
      prompt:
        "Does the code pass a Sentry log level to a PSR-3 logger, as the level of LoggerInterface::log() or by choosing a level method from it, using a LogLevel object, (string) $log->getLevel(), or a Sentry level name such as 'warn', 'fatal', or 'trace', instead of LogLevel::toPsrLevel() or Log::getPsrLevel()?",
      values: [
        {
          value: "TRUE",
          description:
            "A Sentry level value reaches a Psr\\Log\\LoggerInterface without going through toPsrLevel() or getPsrLevel().",
          severity: "warn",
          report:
            "PSR-3 has no `warn`, `fatal`, or `trace` level, so some loggers reject them. Convert with `LogLevel::toPsrLevel()` or `Log::getPsrLevel()` before calling the PSR-3 logger.",
        },
        {
          value: "FALSE",
          description:
            "Every level passed to a PSR-3 logger comes from toPsrLevel(), getPsrLevel(), or a Psr\\Log\\LogLevel constant; or the code passes no level to a PSR-3 logger.",
        },
      ],
    },
  },
} satisfies Suite;

export default {
  model: jev,
  suites: { sdk },
} satisfies Config;

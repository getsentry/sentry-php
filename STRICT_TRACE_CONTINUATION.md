# Strict Trace Continuation Feature

## Overview

The `strictTraceContinuation` feature allows the Sentry PHP SDK to control trace continuation from unknown 3rd party services. When enabled, the SDK will only continue traces from the same organization, preventing 3rd party services from affecting your trace sample rates.

## Problem Statement

When a 3rd party accesses your API and sets headers like `traceparent` and `tracestate`, it can trigger trace propagation on your backend even when your trace sample rate is set to 0%. This can lead to:
- Unexpected trace data from external sources
- Affected sampling rates
- Unwanted trace continuation from different organizations

## Solution

The `strictTraceContinuation` option validates the organization ID from incoming trace headers against your configured DSN's organization ID. If they don't match, a new trace is created instead of continuing the existing one.

## Configuration

### Enable Strict Trace Continuation

```php
use Sentry\ClientBuilder;

$client = ClientBuilder::create([
    'dsn' => 'https://your-key@o123.ingest.sentry.io/project-id',
    'strictTraceContinuation' => true,  // Enable strict validation
])->getClient();
```

### Using with Options Object

```php
use Sentry\Options;

$options = new Options([
    'dsn' => 'https://your-key@o123.ingest.sentry.io/project-id',
]);
$options->enableStrictTraceContinuation(true);
```

## How It Works

1. **Organization ID Extraction**: The SDK extracts the organization ID from your DSN (e.g., `o123` from `o123.ingest.sentry.io`)

2. **Baggage Header Validation**: When receiving trace headers, the SDK checks the `sentry-org_id` entry in the baggage header

3. **Trace Decision**:
   - If `strictTraceContinuation` is **disabled** (default): Continues the trace regardless of org ID
   - If `strictTraceContinuation` is **enabled**:
     - **Matching org IDs**: Continues the existing trace
     - **Mismatched org IDs**: Creates a new trace
     - **Missing org ID**: Continues the trace (backwards compatibility)

## Example Usage

```php
use Sentry\Tracing\TransactionContext;
use function Sentry\continueTrace;

// Incoming headers from a request
$sentryTrace = $_SERVER['HTTP_SENTRY_TRACE'] ?? '';
$baggage = $_SERVER['HTTP_BAGGAGE'] ?? '';

// Continue or create a new trace based on org ID validation
$transactionContext = continueTrace($sentryTrace, $baggage);

// Start a transaction
$transaction = \Sentry\startTransaction($transactionContext);

// Your application logic here...

// Finish the transaction
$transaction->finish();
```

## Behavior Examples

### Scenario 1: Disabled (Default)
```
strictTraceContinuation: false
Incoming org_id: 456
Local org_id: 123
Result: Trace continues (backwards compatible)
```

### Scenario 2: Enabled with Matching Org
```
strictTraceContinuation: true
Incoming org_id: 123
Local org_id: 123
Result: Trace continues
```

### Scenario 3: Enabled with Mismatched Org
```
strictTraceContinuation: true
Incoming org_id: 456
Local org_id: 123
Result: New trace created
```

## Implementation Details

The feature is implemented in:
- `Options::isStrictTraceContinuationEnabled()` - Check if enabled
- `Options::enableStrictTraceContinuation()` - Enable/disable the feature
- `TransactionContext::fromHeaders()` - Validates org ID for transactions
- `PropagationContext::fromHeaders()` - Validates org ID for propagation
- `continueTrace()` - Main entry point for continuing traces

## Compatibility

- **Default**: Disabled (backwards compatible)
- **Minimum PHP Version**: Same as the SDK requirements
- **Sentry SaaS**: Works with org IDs in DSN format `o{orgId}.ingest.sentry.io`
- **Self-hosted**: Works if org ID is configured in the DSN

## Related Documentation

- [Sentry SDK Specification - strictTraceContinuation](https://develop.sentry.dev/sdk/telemetry/traces/#stricttracecontinuation)
- [GitHub Issue #1830](https://github.com/getsentry/sentry-php/issues/1830)
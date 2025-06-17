# Monolog Integration

Sentry provides multiple handlers for integrating with [Monolog](https://github.com/Seldaek/monolog), the popular PHP logging library.

## Available Handlers

### 1. Handler (Events)
The `Sentry\Monolog\Handler` sends log records as Sentry events. This is useful for error tracking and creating issues in Sentry.

### 2. BreadcrumbHandler (Breadcrumbs)
The `Sentry\Monolog\BreadcrumbHandler` adds log records as breadcrumbs to the current scope, providing context for future events.

### 3. LogsHandler (Logs Telemetry) **New**
The `Sentry\Monolog\LogsHandler` sends log records to Sentry's logs telemetry system, which provides structured log aggregation and analysis.

## LogsHandler Setup

### Basic Usage

```php
<?php

use Monolog\Logger;
use Sentry\ClientBuilder;
use Sentry\Monolog\LogsHandler;
use Sentry\SentrySdk;
use Sentry\State\Hub;

// Initialize Sentry with logs enabled
$client = ClientBuilder::create([
    'dsn' => 'YOUR_SENTRY_DSN_HERE',
    'enable_logs' => true,
])->getClient();

$hub = new Hub($client);
SentrySdk::setCurrentHub($hub);

// Create a Monolog logger
$logger = new Logger('app');

// Add the LogsHandler
$logsHandler = new LogsHandler();
$logger->pushHandler($logsHandler);

// Start logging
$logger->info('Application started');
$logger->warning('This is a warning', ['user_id' => 123]);
```

### Constructor Parameters

The `LogsHandler` constructor accepts the following parameters:

```php
public function __construct(
    ?LogsAggregator $logsAggregator = null,  // Custom aggregator instance
    $level = Logger::DEBUG,                   // Minimum log level
    bool $bubble = true,                      // Allow bubbling to other handlers
    bool $includeMonologData = true          // Include Monolog context/extra data
)
```

#### Parameters Explained

- **`$logsAggregator`**: Custom `LogsAggregator` instance. If `null`, a new one is created.
- **`$level`**: Minimum log level to handle (e.g., `Logger::INFO`, `Logger::WARNING`).
- **`$bubble`**: Whether records should bubble up to other handlers in the stack.
- **`$includeMonologData`**: Whether to include Monolog's context and extra data as log attributes.

### Advanced Configuration

```php
<?php

use Monolog\Logger;
use Sentry\Logs\LogsAggregator;
use Sentry\Monolog\LogsHandler;

// Custom aggregator
$aggregator = new LogsAggregator();

// Handler with custom settings
$logsHandler = new LogsHandler(
    $aggregator,        // Use custom aggregator
    Logger::WARNING,    // Only warnings and above
    false,              // Don't bubble to other handlers
    false              // Don't include Monolog data
);

$logger = new Logger('app');
$logger->pushHandler($logsHandler);
```

## Level Mapping

Monolog levels are automatically mapped to Sentry log levels:

| Monolog Level | Sentry Log Level |
|---------------|------------------|
| DEBUG         | debug            |
| INFO          | info             |
| NOTICE        | info             |
| WARNING       | warn             |
| ERROR         | error            |
| CRITICAL      | fatal            |
| ALERT         | fatal            |
| EMERGENCY     | fatal            |

## Attributes and Context

When `$includeMonologData` is `true` (default), the handler adds the following attributes:

### Monolog Metadata
- `monolog.channel`: The logger channel name
- `monolog.level_name`: The original Monolog level name
- `monolog.datetime`: The log record timestamp

### Context Data
Context data is prefixed with `monolog.context.`:
```php
$logger->info('User action', ['user_id' => 123, 'action' => 'login']);
// Results in attributes: monolog.context.user_id=123, monolog.context.action="login"
```

### Extra Data  
Extra data is prefixed with `monolog.extra.`:
```php
// Assuming you have processors that add extra data
// Results in attributes: monolog.extra.memory_usage=1024, monolog.extra.request_id="abc123"
```

### Exception Handling
When exceptions are included in the context, they are automatically expanded:
```php
$logger->error('An error occurred', [
    'exception' => new RuntimeException('Something went wrong')
]);

// Results in attributes:
// monolog.context.exception.class = "RuntimeException"
// monolog.context.exception.message = "Something went wrong"
// monolog.context.exception.file = "/path/to/file.php"
// monolog.context.exception.line = 42
```

## Multiple Handlers

You can combine multiple Sentry handlers for comprehensive logging:

```php
<?php

use Monolog\Logger;
use Sentry\Monolog\Handler;
use Sentry\Monolog\BreadcrumbHandler;
use Sentry\Monolog\LogsHandler;

$logger = new Logger('app');

// Add breadcrumbs for all logs (DEBUG and above)
$logger->pushHandler(new BreadcrumbHandler($hub, Logger::DEBUG));

// Send errors as events (ERROR and above)
$logger->pushHandler(new Handler($hub, Logger::ERROR, false));

// Send logs to telemetry (INFO and above)
$logger->pushHandler(new LogsHandler(null, Logger::INFO));
```

## Manual Flushing

By default, logs are automatically flushed when the script ends. You can also manually flush logs:

```php
$logsHandler = new LogsHandler();
$logger->pushHandler($logsHandler);

$logger->info('Important message');

// Manually flush logs to Sentry
$logsHandler->flush();
```

## Requirements

- Sentry PHP SDK 4.0+
- Monolog 2.0+ or 3.0+
- PHP 7.2+
- Sentry client configured with `enable_logs => true`

## Example: Complete Setup

```php
<?php

require_once 'vendor/autoload.php';

use Monolog\Logger;
use Sentry\ClientBuilder;
use Sentry\Monolog\LogsHandler;
use Sentry\SentrySdk;
use Sentry\State\Hub;

// Initialize Sentry
$client = ClientBuilder::create([
    'dsn' => 'YOUR_SENTRY_DSN_HERE',
    'enable_logs' => true,
    'environment' => 'production',
    'traces_sample_rate' => 1.0,
])->getClient();

$hub = new Hub($client);
SentrySdk::setCurrentHub($hub);

// Setup Monolog with Sentry LogsHandler
$logger = new Logger('app');
$logger->pushHandler(new LogsHandler(null, Logger::INFO));

// Your application code
try {
    $logger->info('Processing started', ['batch_id' => 'batch_123']);
    
    // Simulate some work
    throw new Exception('Something went wrong');
    
} catch (Exception $e) {
    $logger->error('Processing failed', [
        'exception' => $e,
        'batch_id' => 'batch_123'
    ]);
}

$logger->info('Processing completed');
``` 
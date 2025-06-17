<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Level;
use Monolog\Logger;
use Sentry\Logs\LogsAggregator;
use Sentry\Monolog\LogsHandler;

use function Sentry\init;

// Initialize Sentry with logs enabled
init([
    'dsn' => 'YOUR_SENTRY_DSN_HERE',
    'enable_logs' => true,
    'traces_sample_rate' => 1.0,
]);

// Create a Monolog logger
$logger = new Logger('app');

// Option 1: Use LogsHandler with default LogsAggregator
$logsHandler = new LogsHandler(
    null,                    // Use default LogsAggregator
    Level::Info,            // Minimum log level
    true,                    // Allow bubbling to other handlers
    true                     // Include Monolog context and extra data
);

$logger->pushHandler($logsHandler);

// Option 2: Use LogsHandler with custom LogsAggregator
$customAggregator = new LogsAggregator();
$customLogsHandler = new LogsHandler(
    $customAggregator,       // Custom aggregator
    Level::Warning,         // Only capture warnings and above
    false,                   // Don't bubble to other handlers
    false                    // Don't include Monolog data
);

// For this example, we'll use the first handler
// $logger->pushHandler($customLogsHandler);

// Example log messages
$logger->info('Application started');
$logger->warning('This is a warning message', ['user_id' => 123]);
$logger->error('An error occurred', [
    'exception' => new RuntimeException('Something went wrong'),
    'request_id' => 'req_abc123',
]);

// Add some extra context
$logger->info('User action', [
    'action' => 'login',
    'user_id' => 456,
    'ip_address' => '192.168.1.1',
]);

// Logs are automatically flushed when the script ends,
// but you can also manually flush them:
$logsHandler->flush();

echo "Logs have been sent to Sentry!\n";
echo 'Total logs in aggregator: ' . count($logsHandler->getLogsAggregator()->all()) . "\n";

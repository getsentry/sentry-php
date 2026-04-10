--TEST--
Test that OOM handling does not capture warnings from the memory limit increase attempt
--INI--
memory_limit=67108864
--FILE--
<?php

declare(strict_types=1);

namespace Sentry {
    // override the function so that we can trace how often it got invoked
    function ini_set(string $option, string $value)
    {
        if (strtolower($option) !== 'memory_limit') {
            return \ini_set($option, $value);
        }

        $GLOBALS['sentry_test_ini_set_calls'] = ($GLOBALS['sentry_test_ini_set_calls'] ?? 0) + 1;

        return \ini_set($option, $value);
    }

    // override the function so that we can test if the memory gets increased to a value
    // that is lower than currently in use
    function memory_get_usage(bool $realUsage = false): int
    {
        return $GLOBALS['sentry_test_memory_get_usage'] ?? \memory_get_usage($realUsage);
    }
}

namespace Sentry\Tests {
    use Sentry\Event;
    use Sentry\Transport\Result;
    use Sentry\Transport\ResultStatus;
    use Sentry\Transport\TransportInterface;

    $vendor = __DIR__;

    while (!file_exists($vendor . '/vendor')) {
        $vendor = \dirname($vendor);
    }

    require $vendor . '/vendor/autoload.php';

    error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);

    $GLOBALS['sentry_test_memory_get_usage'] = 1;

    set_error_handler(static function (int $level): bool {
        if (\E_WARNING !== $level) {
            return false;
        }

        $GLOBALS['sentry_test_warning_handler_calls'] = ($GLOBALS['sentry_test_warning_handler_calls'] ?? 0) + 1;

        return true;
    });

    $transport = new class implements TransportInterface {
        public function send(Event $event): Result
        {
            $GLOBALS['sentry_test_transport_calls'] = ($GLOBALS['sentry_test_transport_calls'] ?? 0) + 1;

            return new Result(ResultStatus::success());
        }

        public function close(?int $timeout = null): Result
        {
            return new Result(ResultStatus::success());
        }
    };

    \Sentry\init([
        'dsn' => 'http://public@example.com/sentry/1',
        'transport' => $transport,
        'capture_silenced_errors' => true,
    ]);

    register_shutdown_function(static function (): void {
        echo 'Transport calls: ' . ($GLOBALS['sentry_test_transport_calls'] ?? 0) . \PHP_EOL;
        echo 'Memory limit increase attempts: ' . ($GLOBALS['sentry_test_ini_set_calls'] ?? 0) . \PHP_EOL;
        echo 'Warning handler calls: ' . ($GLOBALS['sentry_test_warning_handler_calls'] ?? 0) . \PHP_EOL;
    });

    $foo = str_repeat('x', 1024 * 1024 * 1024);
}
?>
--EXPECTF--
%A
Transport calls: 1
Memory limit increase attempts: 1
Warning handler calls: 0

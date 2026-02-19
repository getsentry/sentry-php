<?php

declare(strict_types=1);

use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Scope;

use function Sentry\configureScope;
use function Sentry\getTraceparent;
use function Sentry\init;
use function Sentry\withContext;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

ignore_user_abort(true);

init([
    'dsn' => false,
    'default_integrations' => false,
]);

configureScope(static function (Scope $scope): void {
    $scope->setTag('baseline', 'yes');
});

$handler = static function (): void {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH);

    if ($path === '/ping') {
        header('Content-Type: text/plain');
        echo 'pong';

        return;
    }

    if ($path !== '/scope') {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'not found';

        return;
    }

    $requestTag = isset($_GET['request']) ? (string) $_GET['request'] : 'none';
    $leakTag = isset($_GET['leak']) ? (string) $_GET['leak'] : null;

    withContext(static function () use ($requestTag, $leakTag): void {
        configureScope(static function (Scope $scope) use ($requestTag, $leakTag): void {
            $scope->setTag('request', $requestTag);

            if ($leakTag !== null) {
                $scope->setTag('leak', $leakTag);
            }
        });

        $event = Event::createEvent();
        configureScope(static function (Scope $scope) use (&$event): void {
            $event = $scope->applyToEvent($event);
        });

        $tags = [];

        if ($event !== null) {
            $tags = $event->getTags();
        }

        header('Content-Type: application/json');
        echo json_encode([
            'runtime_context_id' => SentrySdk::getCurrentRuntimeContext()->getId(),
            'traceparent' => getTraceparent(),
            'tags' => $tags,
        ]);
    });
};

while (true) {
    $keepRunning = frankenphp_handle_request($handler);
    gc_collect_cycles();

    if (!$keepRunning) {
        break;
    }
}

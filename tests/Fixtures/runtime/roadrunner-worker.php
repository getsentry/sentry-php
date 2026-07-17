<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Sentry\Event;
use Sentry\SentrySdk;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

use function Sentry\getTraceparent;
use function Sentry\init;
use function Sentry\withContext;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$requiredClasses = [
    Worker::class,
    PSR7Worker::class,
    Psr17Factory::class,
    Response::class,
];

foreach ($requiredClasses as $class) {
    if (!class_exists($class)) {
        fwrite(\STDERR, sprintf("Required class '%s' is not available for RoadRunner fixture worker.\n", $class));

        exit(2);
    }
}

init([
    'dsn' => false,
    'default_integrations' => false,
]);

SentrySdk::getGlobalScope()->setTag('baseline', 'yes');

$factory = new Psr17Factory();
$worker = Worker::create();
$psrWorker = createPsrWorker($worker, $factory);

while (true) {
    try {
        $request = $psrWorker->waitRequest();
    } catch (Throwable $exception) {
        $worker->error((string) $exception);

        continue;
    }

    if ($request === null) {
        break;
    }

    try {
        $response = handleRequest($request);
    } catch (Throwable $exception) {
        $worker->error((string) $exception);
        $response = new Response(500, ['Content-Type' => 'text/plain'], 'internal error');
    }

    try {
        $psrWorker->respond($response);
    } catch (Throwable $exception) {
        $worker->error((string) $exception);
    }
}

function createPsrWorker($worker, $factory)
{
    $reflectionClass = new ReflectionClass(PSR7Worker::class);
    $constructor = $reflectionClass->getConstructor();
    $requiredParameterCount = $constructor !== null ? $constructor->getNumberOfRequiredParameters() : 0;

    $arguments = [$worker, $factory, $factory, $factory, $factory];

    return $reflectionClass->newInstanceArgs(array_slice($arguments, 0, $requiredParameterCount));
}

function handleRequest($request): Response
{
    $path = $request->getUri()->getPath();

    if ($path === '/ping') {
        return new Response(200, ['Content-Type' => 'text/plain'], 'pong');
    }

    if ($path !== '/scope') {
        return new Response(404, ['Content-Type' => 'text/plain'], 'not found');
    }

    $query = [];
    parse_str($request->getUri()->getQuery(), $query);

    $requestTag = isset($query['request']) ? (string) $query['request'] : 'none';
    $leakTag = isset($query['leak']) ? (string) $query['leak'] : null;

    $payload = withContext(static function () use ($requestTag, $leakTag): string {
        SentrySdk::getIsolationScope()->setTag('request', $requestTag);

        if ($leakTag !== null) {
            SentrySdk::getIsolationScope()->setTag('leak', $leakTag);
        }

        $event = Event::createEvent();
        $event = SentrySdk::getGlobalScope()->merge(SentrySdk::getIsolationScope())->applyToEvent($event);

        $tags = [];

        if ($event !== null) {
            $tags = $event->getTags();
        }

        $encoded = json_encode([
            'runtime_context_id' => SentrySdk::getCurrentRuntimeContext()->getId(),
            'traceparent' => getTraceparent(),
            'tags' => $tags,
        ]);

        if ($encoded === false) {
            return '{}';
        }

        return $encoded;
    });

    return new Response(200, ['Content-Type' => 'application/json'], $payload);
}

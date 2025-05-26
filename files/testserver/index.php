<?php

declare(strict_types=1);

$outputFile = __DIR__ . '/output.json';

// We expect the path to be `/api/<project_id>/envelope/`.
// We use the project ID to determine the status code so we need to extract it from the path
$path = trim(parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH), '/');

if (strpos($path, 'ping') === 0) {
    http_response_code(200);

    echo 'pong';

    return;
}

if (!preg_match('/api\/\d+\/envelope/', $path)) {
    http_response_code(204);

    return;
}

$status = (int) explode('/', $path)[1];

$headers = getallheaders();

$rawBody = file_get_contents('php://input');

$compressed = false;

if (!isset($headers['Content-Encoding'])) {
    $body = $rawBody;
} elseif ($headers['Content-Encoding'] === 'gzip') {
    $body = gzdecode($rawBody);
    $compressed = true;
} else {
    $body = '__unable to decode body__';
}

$output = [
    'body' => $body,
    'status' => $status,
    'server' => $_SERVER,
    'headers' => $headers,
    'compressed' => $compressed,
];

file_put_contents(__DIR__ . '/output.json', json_encode($output, \JSON_PRETTY_PRINT));

header('X-Sentry-Test-Server-Status-Code: ' . $status);

http_response_code($status);

echo $body;

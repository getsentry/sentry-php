<?php

declare(strict_types=1);

// This is a tiny fake agent used to test the AgentClient.
// It accepts the same 4-byte big-endian length-prefixed payloads as the real agent
// and writes all received envelopes to a JSON file for the test process to inspect.

if ($argc < 3) {
    fwrite(\STDERR, "Usage: php agent-server.php <port> <output-file>\n");

    exit(1);
}

$port = (int) $argv[1];
$outputFile = $argv[2];

$server = @stream_socket_server("tcp://127.0.0.1:{$port}", $errorNo, $errorMessage);

if ($server === false) {
    fwrite(\STDERR, sprintf("Failed to start test agent server: [%d] %s\n", $errorNo, $errorMessage));

    exit(1);
}

$messages = [];
$connections = 0;

$writeOutput = static function () use (&$messages, &$connections, $outputFile): void {
    file_put_contents($outputFile, json_encode([
        'messages' => $messages,
        'connections' => $connections,
    ]));
};

$writeOutput();

while ($connection = @stream_socket_accept($server, -1)) {
    ++$connections;
    $writeOutput();

    $buffer = '';
    $messageLength = 0;

    while (!feof($connection)) {
        $chunk = fread($connection, 8192);

        if ($chunk === false) {
            break;
        }

        if ($chunk === '') {
            continue;
        }

        $buffer .= $chunk;

        while (\strlen($buffer) >= 4) {
            if ($messageLength === 0) {
                $unpackedHeader = unpack('N', substr($buffer, 0, 4));

                if ($unpackedHeader === false) {
                    break 2;
                }

                $messageLength = $unpackedHeader[1];
            }

            if (\strlen($buffer) < $messageLength) {
                break;
            }

            $messages[] = substr($buffer, 4, $messageLength - 4);
            $buffer = (string) substr($buffer, $messageLength);
            $messageLength = 0;

            $writeOutput();
        }
    }

    fclose($connection);
}

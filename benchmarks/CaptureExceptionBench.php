<?php

declare(strict_types=1);

namespace Sentry\Benchmarks;

use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;
use Tally\Attribute\After;
use Tally\Attribute\Before;
use Tally\Attribute\Warmup;

use function Sentry\captureException;
use function Sentry\init;

/**
 * Measures captureException() from the global helper down to the serialized
 * envelope. The real transport runs, only the HTTP request itself is replaced.
 */
final class CaptureExceptionBench
{
    /**
     * Frames added on top of Tally's own, so the stack trace has the depth of
     * an exception thrown inside an application.
     */
    private const STACK_DEPTH = 20;

    private CountingHttpClient $httpClient;

    private \Throwable $exception;

    #[Before]
    public function setUp(): void
    {
        $this->httpClient = new CountingHttpClient();

        init([
            'dsn' => 'https://public@example.com/1',
            'http_client' => $this->httpClient,
        ]);

        $this->exception = $this->createException(self::STACK_DEPTH);
    }

    #[Name('captureException with 20 recursive frames')]
    #[Warmup(1)]
    public function benchCaptureException(): void
    {
        captureException($this->exception);
    }

    /**
     * Without this, an event dropped before the transport would be measured
     * as a very fast capture.
     */
    #[After]
    public function assertEnvelopeWasSent(): void
    {
        if ($this->httpClient->requests === 0) {
            throw new \LogicException('captureException() did not reach the HTTP client.');
        }
    }

    private function createException(int $depth): \Throwable
    {
        if ($depth > 0) {
            return $this->createException($depth - 1);
        }

        return new \RuntimeException('Benchmark exception');
    }
}

final class CountingHttpClient implements HttpClientInterface
{
    public int $requests = 0;

    public function sendRequest(Request $request, Options $options): Response
    {
        ++$this->requests;

        return new Response(200, [], '');
    }
}

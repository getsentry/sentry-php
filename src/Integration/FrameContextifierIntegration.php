<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Psr\Log\LoggerInterface;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\Stacktrace;
use Sentry\State\Scope;

/**
 * This integration reads excerpts of code around the line that originated an
 * error.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class FrameContextifierIntegration implements IntegrationInterface
{
    /**
     * @var int The maximum number of lines of code to read
     */
    private $contextLines;

    /**
     * @var LoggerInterface|null A PSR-3 logger
     */
    private $logger;

    /**
     * Creates a new instance of this integration.
     *
     * @param int             $contextLines The maximum number of lines of code to read
     * @param LoggerInterface $logger       A PSR-3 logger
     */
    public function __construct(int $contextLines = 5, ?LoggerInterface $logger = null)
    {
        if ($contextLines < 0) {
            throw new \InvalidArgumentException(sprintf('The value of the $maxLinesToFetch argument must be greater than or equal to 0. Got: "%d".', $contextLines));
        }

        $this->contextLines = $contextLines;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event): Event {
            $client = SentrySdk::getCurrentHub()->getClient();

            // Avoid doing double work if the deprecated `context_lines` option
            // is still in use. Since the option can change at runtime, it's safer
            // to check its value here every time rather than when deciding whether
            // the integration should be added or not to the client
            if (null === $client || null !== $client->getOptions()->getContextLines(false)) {
                return $event;
            }

            $integration = $client->getIntegration(self::class);

            if (null === $integration) {
                return $event;
            }

            if (null !== $event->getStacktrace()) {
                $integration->addContextToStacktraceFrames($event->getStacktrace());
            }

            foreach ($event->getExceptions() as $exception) {
                if (!isset($exception['stacktrace'])) {
                    continue;
                }

                $integration->addContextToStacktraceFrames($exception['stacktrace']);
            }

            return $event;
        });
    }

    /**
     * Contextifies the frames of the given stacktrace.
     *
     * @param Stacktrace $stacktrace The stacktrace object
     */
    private function addContextToStacktraceFrames(Stacktrace $stacktrace): void
    {
        foreach ($stacktrace->getFrames() as $frame) {
            if ($frame->isInternal()) {
                continue;
            }

            $sourceCodeExcerpt = $this->getSourceCodeExcerpt($frame->getAbsoluteFilePath(), $frame->getLine());

            $frame->setPreContext($sourceCodeExcerpt['pre_context']);
            $frame->setContextLine($sourceCodeExcerpt['context_line']);
            $frame->setPostContext($sourceCodeExcerpt['post_context']);
        }
    }

    /**
     * Gets an excerpt of the source code around a given line.
     *
     * @param string $filePath   The file path
     * @param int    $lineNumber The line to centre about
     *
     * @return array<string, mixed>
     *
     * @psalm-return array{
     *     pre_context: string[],
     *     context_line: string|null,
     *     post_context: string[]
     * }
     */
    private function getSourceCodeExcerpt(string $filePath, int $lineNumber): array
    {
        $frame = [
            'pre_context' => [],
            'context_line' => null,
            'post_context' => [],
        ];

        $target = max(0, ($lineNumber - ($this->contextLines + 1)));
        $currentLineNumber = $target + 1;

        try {
            $file = new \SplFileObject($filePath);
            $file->seek($target);

            while (!$file->eof()) {
                /** @var string $line */
                $line = $file->current();
                $line = rtrim($line, "\r\n");

                if ($currentLineNumber === $lineNumber) {
                    $frame['context_line'] = $line;
                } elseif ($currentLineNumber < $lineNumber) {
                    $frame['pre_context'][] = $line;
                } elseif ($currentLineNumber > $lineNumber) {
                    $frame['post_context'][] = $line;
                }

                ++$currentLineNumber;

                if ($currentLineNumber > $lineNumber + $this->contextLines) {
                    break;
                }

                $file->next();
            }
        } catch (\Throwable $exception) {
            if (null !== $this->logger) {
                $this->logger->warning(sprintf('Failed to get the source code excerpt for the file "%s".', $filePath));
            }
        }

        return $frame;
    }
}

<?php

declare(strict_types=1);

namespace Sentry\Util;

use Sentry\Frame;
use Sentry\FrameBuilder;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializerInterface;

/**
 * Resolves code location metadata from backtraces.
 *
 * @internal
 *
 * @phpstan-import-type StacktraceFrame from FrameBuilder
 */
final class CodeLocationResolver
{
    /**
     * @var FrameBuilder An instance of the builder of {@see Frame} objects
     */
    private $frameBuilder;

    /**
     * Constructor.
     *
     * @param Options                           $options                  The SDK client options
     * @param RepresentationSerializerInterface $representationSerializer The representation serializer
     */
    public function __construct(Options $options, RepresentationSerializerInterface $representationSerializer)
    {
        $this->frameBuilder = new FrameBuilder($options, $representationSerializer);
    }

    /**
     * Resolves the first in-app frame from the current backtrace into code
     * location metadata.
     *
     * @return array{'code.filepath': string, 'code.function': string|null, 'code.lineno': int}|null
     */
    public function resolve(int $limit = 20): ?array
    {
        /** @var list<StacktraceFrame> $backtrace */
        $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $limit);

        return $this->resolveFromBacktrace($backtrace);
    }

    /**
     * Resolves the first in-app frame from a backtrace into code location metadata.
     *
     * @param array<int, array<string, mixed>> $backtrace The backtrace
     *
     * @phpstan-param list<StacktraceFrame> $backtrace
     *
     * @return array{'code.filepath': string, 'code.function': string|null, 'code.lineno': int}|null
     */
    public function resolveFromBacktrace(array $backtrace): ?array
    {
        $frame = $this->findFirstInAppFrameForBacktrace($backtrace);

        if ($frame === null) {
            return null;
        }

        return $this->getCodeLocationForFrame($frame);
    }

    /**
     * Find the first in-app frame for a given backtrace.
     *
     * @param array<int, array<string, mixed>> $backtrace The backtrace
     *
     * @phpstan-param list<StacktraceFrame> $backtrace
     */
    public function findFirstInAppFrameForBacktrace(array $backtrace): ?Frame
    {
        $file = Frame::INTERNAL_FRAME_FILENAME;
        $line = 0;

        foreach ($backtrace as $backtraceFrame) {
            $frame = $this->frameBuilder->buildFromBacktraceFrame($file, $line, $backtraceFrame);

            if ($frame->isInApp()) {
                return $frame;
            }

            $file = $backtraceFrame['file'] ?? Frame::INTERNAL_FRAME_FILENAME;
            $line = $backtraceFrame['line'] ?? 0;
        }

        return null;
    }

    /**
     * Converts a frame into code location metadata.
     *
     * @return array{'code.filepath': string, 'code.function': string|null, 'code.lineno': int}
     */
    public function getCodeLocationForFrame(Frame $frame): array
    {
        return [
            'code.filepath' => $frame->getFile(),
            'code.function' => $frame->getFunctionName(),
            'code.lineno' => $frame->getLine(),
        ];
    }
}

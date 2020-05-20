<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This class represents a single frame of a stacktrace.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Frame implements \JsonSerializable
{
    private const INTERNAL_FRAME_FILENAME = '[internal]';

    /**
     * @var string|null The name of the function being called
     */
    private $functionName;

    /**
     * @var string The file where the frame originated
     */
    private $file;

    /**
     * @var string The absolute path to the source file
     */
    private $absoluteFilePath;

    /**
     * @var int The line at which the frame originated
     */
    private $line;

    /**
     * @var string[] A list of source code lines before the one where the frame
     *               originated
     */
    private $preContext = [];

    /**
     * @var string|null The source code written at the line number of the file that
     *                  originated this frame
     */
    private $contextLine;

    /**
     * @var string[] A list of source code lines after the one where the frame
     *               originated
     */
    private $postContext = [];

    /**
     * @var bool Flag telling whether the frame is related to the execution of
     *           the relevant code in this stacktrace
     */
    private $inApp;

    /**
     * @var array<string, mixed> A mapping of variables which were available within
     *                    this frame (usually context-locals)
     */
    private $vars = [];

    /**
     * Initializes a new instance of this class using the provided information.
     *
     * @param string|null          $functionName     The name of the function being called
     * @param string               $file             The file where the frame originated
     * @param string|null          $absoluteFilePath The absolute path to the source file
     * @param int                  $line             The line at which the frame originated
     * @param array<string, mixed> $vars             A mapping of variables which were available
     *                                               within the frame
     * @param bool                 $inApp            Whether the frame is related to the
     *                                               execution of code relevant to the
     *                                               application
     */
    public function __construct(?string $functionName, string $file, int $line, ?string $absoluteFilePath = null, array $vars = [], bool $inApp = true)
    {
        $this->functionName = $functionName;
        $this->file = $file;
        $this->absoluteFilePath = $absoluteFilePath ?? $file;
        $this->line = $line;
        $this->vars = $vars;
        $this->inApp = $inApp;
    }

    /**
     * Gets the name of the function being called.
     */
    public function getFunctionName(): ?string
    {
        return $this->functionName;
    }

    /**
     * Gets the file where the frame originated.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Gets the absolute path to the source file.
     */
    public function getAbsoluteFilePath(): string
    {
        return $this->absoluteFilePath;
    }

    /**
     * Gets the line at which the frame originated.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Gets a list of source code lines before the one where the frame originated.
     *
     * @return string[]
     */
    public function getPreContext(): array
    {
        return $this->preContext;
    }

    /**
     * Sets a list of source code lines before the one where the frame originated.
     *
     * @param string[] $preContext The source code lines
     */
    public function setPreContext(array $preContext): void
    {
        $this->preContext = $preContext;
    }

    /**
     * Gets the source code written at the line number of the file that originated
     * this frame.
     */
    public function getContextLine(): ?string
    {
        return $this->contextLine;
    }

    /**
     * Sets the source code written at the line number of the file that originated
     * this frame.
     *
     * @param string|null $contextLine The source code line
     */
    public function setContextLine(?string $contextLine): void
    {
        $this->contextLine = $contextLine;
    }

    /**
     * Gets a list of source code lines after the one where the frame originated.
     *
     * @return string[]
     */
    public function getPostContext(): array
    {
        return $this->postContext;
    }

    /**
     * Sets a list of source code lines after the one where the frame originated.
     *
     * @param string[] $postContext The source code lines
     */
    public function setPostContext(array $postContext): void
    {
        $this->postContext = $postContext;
    }

    /**
     * Gets whether the frame is related to the execution of the relevant code
     * in this stacktrace.
     */
    public function isInApp(): bool
    {
        return $this->inApp;
    }

    /**
     * Sets whether the frame is related to the execution of the relevant code
     * in this stacktrace.
     *
     * @param bool $inApp flag indicating whether the frame is application-related
     */
    public function setIsInApp(bool $inApp): void
    {
        $this->inApp = $inApp;
    }

    /**
     * Gets a mapping of variables which were available within this frame
     * (usually context-locals).
     *
     * @return array<string, mixed>
     */
    public function getVars(): array
    {
        return $this->vars;
    }

    /**
     * Sets a mapping of variables which were available within this frame
     * (usually context-locals).
     *
     * @param array<string, mixed> $vars The variables
     */
    public function setVars(array $vars): void
    {
        $this->vars = $vars;
    }

    /**
     * Gets whether the frame is internal.
     */
    public function isInternal(): bool
    {
        return self::INTERNAL_FRAME_FILENAME === $this->file;
    }

    /**
     * Returns an array representation of the data of this frame modeled according
     * to the specifications of the Sentry SDK Stacktrace Interface.
     *
     * @psalm-return array{
     *     function: string|null,
     *     filename: string,
     *     lineno: int,
     *     in_app: bool,
     *     abs_path: string,
     *     pre_context?: string[],
     *     context_line?: string,
     *     post_context?: string[],
     *     vars?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $result = [
            'function' => $this->functionName,
            'filename' => $this->file,
            'lineno' => $this->line,
            'in_app' => $this->inApp,
            'abs_path' => $this->absoluteFilePath,
        ];

        if (0 !== \count($this->preContext)) {
            $result['pre_context'] = $this->preContext;
        }

        if (null !== $this->contextLine) {
            $result['context_line'] = $this->contextLine;
        }

        if (0 !== \count($this->postContext)) {
            $result['post_context'] = $this->postContext;
        }

        if (!empty($this->vars)) {
            $result['vars'] = $this->vars;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

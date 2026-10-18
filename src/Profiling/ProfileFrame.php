<?php

declare(strict_types=1);

namespace Sentry\Profiling;

/**
 * @internal
 */
final class ProfileFrame
{
    /**
     * @var string The absolute path to the source file
     */
    private $absPath;

    /**
     * @var string The path to the source file with the configured prefixes stripped
     */
    private $filename;

    /**
     * @var string The name of the function being called
     */
    private $function;

    /**
     * @var string|null The class the function belongs to, if any
     */
    private $module;

    /**
     * @var int|null The line number in the source file
     */
    private $lineno;

    public function __construct(string $absPath, string $filename, string $function, ?string $module, ?int $lineno)
    {
        $this->absPath = $absPath;
        $this->filename = $filename;
        $this->function = $function;
        $this->module = $module;
        $this->lineno = $lineno;
    }

    public function getAbsPath(): string
    {
        return $this->absPath;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getFunction(): string
    {
        return $this->function;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function getLineno(): ?int
    {
        return $this->lineno;
    }
}

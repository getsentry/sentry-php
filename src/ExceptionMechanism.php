<?php

declare(strict_types=1);

namespace Sentry;

/**
 * The exception mechanism is an optional field residing in the Exception Interface.
 * It carries additional information about the way the exception was created on the
 * target system. This includes general exception values obtained from operating
 * system or runtime APIs, as well as mechanism-specific values.
 */
final class ExceptionMechanism
{
    public const TYPE_GENERIC = 'generic';

    /**
     * @var string Unique identifier of this mechanism determining rendering and
     *             processing of the mechanism data
     */
    private $type;

    /**
     * @var bool Flag indicating whether the exception has been handled by the
     *           user (e.g. via try..catch)
     */
    private $handled;

    /**
     * Class constructor.
     *
     * @param string $type    Unique identifier of this mechanism determining
     *                        rendering and processing of the mechanism data
     * @param bool   $handled Flag indicating whether the exception has been
     *                        handled by the user (e.g. via try..catch)
     */
    public function __construct(string $type, bool $handled)
    {
        $this->type = $type;
        $this->handled = $handled;
    }

    /**
     * Returns the unique identifier of this mechanism determining rendering and
     * processing of the mechanism data.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the flag indicating whether the exception has been handled by the
     * user (e.g. via try..catch).
     */
    public function isHandled(): bool
    {
        return $this->handled;
    }
}

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
     * @var array<string, mixed> Arbitrary extra data that might help the user
     *                           understand the error thrown by this mechanism
     */
    private $data;

    private $exceptionId;

    private $parentId;

    private $isExceptionGroup;

    /**
     * Class constructor.
     *
     * @param string               $type    Unique identifier of this mechanism determining
     *                                      rendering and processing of the mechanism data
     * @param bool                 $handled Flag indicating whether the exception has been
     *                                      handled by the user (e.g. via try..catch)
     * @param array<string, mixed> $data    Arbitrary extra data that might help the user
     *                                      understand the error thrown by this mechanism
     */
    public function __construct(string $type, bool $handled, array $data = [])
    {
        $this->type = $type;
        $this->handled = $handled;
        $this->data = $data;
        $this->exceptionId = 0;
        $this->parentId = null;
        $this->isExceptionGroup = false;
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

    /**
     * Returns arbitrary extra data that might help the user understand the error
     * thrown by this mechanism.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Sets the arbitrary extra data.
     *
     * @param array<string, mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getExceptionId(): int
    {
        return $this->exceptionId;
    }

    public function setExceptionId(int $exceptionId): self
    {
        $this->exceptionId = $exceptionId;

        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function getIsExceptionGroup(): bool
    {
        return $this->isExceptionGroup;
    }

    public function setIsExceptionGroup(bool $isExceptionGroup): self
    {
        $this->isExceptionGroup = $isExceptionGroup;

        return $this;
    }
}

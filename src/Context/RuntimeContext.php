<?php

declare(strict_types=1);

namespace Sentry\Context;

/**
 * This class stores information about the current runtime.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RuntimeContext
{
    /**
     * @var string The name of the runtime
     */
    private $name;

    /**
     * @var string|null The version of the runtime
     */
    private $version;

    /**
     * Constructor.
     *
     * @param string      $name    The name of the runtime
     * @param string|null $version The version of the runtime
     */
    public function __construct(string $name, ?string $version = null)
    {
        if (0 === \strlen(trim($name))) {
            throw new \InvalidArgumentException('The $name argument cannot be an empty string.');
        }

        $this->name = $name;
        $this->version = $version;
    }

    /**
     * Gets the name of the runtime.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name of the runtime.
     *
     * @param string $name The name
     */
    public function setName(string $name): void
    {
        if (0 === \strlen(trim($name))) {
            throw new \InvalidArgumentException('The $name argument cannot be an empty string.');
        }

        $this->name = $name;
    }

    /**
     * Gets the version of the runtime.
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Sets the version of the runtime.
     *
     * @param string|null $version The version
     */
    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }
}

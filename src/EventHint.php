<?php

declare(strict_types=1);

namespace Sentry;

/**
 * This class represents hints on how to process an event.
 */
final class EventHint
{
    /**
     * The original exception to add to the event.
     *
     * @var \Throwable|null
     */
    public $exception;

    /**
     * The stacktrace to set on the event.
     *
     * @var Stacktrace|null
     */
    public $stacktrace;

    /**
     * Any extra data that might be needed to process the event.
     *
     * @var array<string, mixed>
     */
    public $extra = [];

    /**
     * Create a EventHint instance from an array of values.
     *
     * @psalm-param array{
     *     exception?: \Throwable,
     *     stacktrace?: Stacktrace|null,
     *     extra?: array<string, mixed>
     * } $hintData
     */
    public static function fromArray(array $hintData): self
    {
        $hint = new self();

        foreach ($hintData as $hintKey => $hintValue) {
            if (!property_exists($hint, $hintKey)) {
                throw new \InvalidArgumentException(sprintf('There is no EventHint attribute called "%s".', $hintKey));
            }

            $hint->{$hintKey} = $hintValue;
        }

        return $hint;
    }
}

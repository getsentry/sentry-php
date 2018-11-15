<?php

declare(strict_types=1);

namespace Sentry\Context;

/**
 * This class is a specialized version of the base `Context` adapted to work
 * for the tags context.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class TagsContext extends Context
{
    /**
     * {@inheritdoc}
     */
    public function merge(array $data, bool $recursive = false): void
    {
        if ($recursive) {
            throw new \InvalidArgumentException('The tags context does not allow recursive merging of its data.');
        }

        foreach ($data as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('The $data argument must contains a simple array of string values.');
            }
        }

        parent::merge($data);
    }

    /**
     * {@inheritdoc}
     */
    public function setData(array $data): void
    {
        foreach ($data as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('The $data argument must contains a simple array of string values.');
            }
        }

        parent::setData($data);
    }

    /**
     * {@inheritdoc}
     */
    public function replaceData(array $data): void
    {
        foreach ($data as $value) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('The $data argument must contains a simple array of string values.');
            }
        }

        parent::replaceData($data);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value): void
    {
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('The $value argument must be a string.');
        }

        parent::offsetSet($offset, $value);
    }
}

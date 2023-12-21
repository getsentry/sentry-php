<?php

declare(strict_types=1);

namespace Sentry\Metrics\Types;

use Sentry\Metrics\MetricsUnit;

/**
 * @internal
 */
final class SetType extends AbstractType
{
    /**
     * @var string
     */
    public const TYPE = 's';

    /**
     * @var array<array-key, int|string>
     */
    private $values;

    /**
     * @param int|string $value
     */
    public function __construct(string $key, $value, MetricsUnit $unit, array $tags, int $timestamp)
    {
        parent::__construct($key, $unit, $tags, $timestamp);

        $this->add($value);
    }

    /**
     * @param int|string $value
     */
    public function add($value): void
    {
        $this->values[] = $value;
    }

    public function serialize(): array
    {
        foreach ($this->values as $key => $value) {
            if (\is_string($value)) {
                $this->values[$key] = crc32($value);
            }
        }

        return $this->values;
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}

<?php

declare(strict_types=1);

namespace Sentry\ClientReport;

class ClientReport
{
    /**
     * @var string
     */
    private $reason;

    /**
     * @var string
     */
    private $category;

    /**
     * @var int
     */
    private $quantity;

    public function __construct(string $category, string $reason, int $quantity)
    {
        $this->category = $category;
        $this->reason = $reason;
        $this->quantity = $quantity;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

<?php

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

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

}

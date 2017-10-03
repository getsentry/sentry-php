<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven;

class TransactionStack
{
    /**
     * @var array
     */
    public $stack;

    public function __construct()
    {
        $this->stack = [];
    }

    public function clear()
    {
        $this->stack = [];
    }

    public function peek()
    {
        $len = count($this->stack);
        if (0 === $len) {
            return null;
        }

        return $this->stack[$len - 1];
    }

    public function push($context)
    {
        $this->stack[] = $context;
    }

    /** @noinspection PhpInconsistentReturnPointsInspection
     * @param string|null $context
     *
     * @return mixed
     */
    public function pop($context = null)
    {
        if (!$context) {
            return array_pop($this->stack);
        }
        while (!empty($this->stack)) {
            if (array_pop($this->stack) === $context) {
                return $context;
            }
        }
        // @codeCoverageIgnoreStart
    }

    // @codeCoverageIgnoreEnd
}

<?php

namespace Raven\Request\Interfaces;
use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class Exception implements ToArrayInterface
{
    /**
     * @var SingleException[]
     */
    private $exceptions;

    public function __construct(array $exceptions)
    {
        foreach ($exceptions as $exception) {
            if (!$exception instanceof SingleException) {
                throw new \InvalidArgumentException(
                    'Exception exceptions should be instance of Raven\Request\Interfaces\SingleException'
                );
            }
        }

        $this->exceptions = $exceptions;
    }

    /**
     * @return SingleException[]
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array_map(function (SingleException $exception) {
            return $exception->toArray();
        }, $this->getExceptions());
    }
}

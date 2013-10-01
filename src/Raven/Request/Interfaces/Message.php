<?php

namespace Raven\Request\Interfaces;

use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class Message implements ToArrayInterface
{
    /**
     * @var string
     */
    private $message;

    /**
     * @var array|null
     */
    private $params;

    public function __construct($message, array $params = null)
    {
        $this->message = $message;
        $this->params = $params;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return array|null
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array_filter(array(
            'message' => $this->getMessage(),
            'params' => $this->getParams(),
        ));
    }
}

<?php

namespace Raven\Request\Interfaces;
use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class Query implements ToArrayInterface
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var string|null
     */
    private $engine;

    public function __construct($query, $engine = null)
    {
        $this->query = $query;
        $this->engine = $engine;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string|null
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array_filter(array(
            'query' => $this->getQuery(),
            'engine' => $this->getEngine(),
        ));
    }
}

<?php

namespace Raven\Request\Interfaces;
use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class StackTrace implements ToArrayInterface
{
    /**
     * @var Frame[]
     */
    private $frames;

    public function __construct(array $frames)
    {
        foreach ($frames as $frame) {
            if (!$frame instanceof Frame && !$frame instanceof Template) {
                throw new \InvalidArgumentException(
                    'StackTrace frames should be instance of Raven\Request\Interfaces\Frames'
                );
            }
        }

        $this->frames = $frames;
    }

    /**
     * @return Frame[]
     */
    public function getFrames()
    {
        return $this->frames;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array(
            'frames' => array_map(function (Frame $frame) {
                return $frame->toArray();
            }, $this->getFrames()),
        );
    }
}

<?php

namespace Raven\Request\Interfaces;
use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class Template implements ToArrayInterface
{
    /**
     * @var string
     */
    private $filename;

    /**
     * @var string
     */
    private $contextLine;

    /**
     * @var integer
     */
    private $lineNumber;

    /**
     * @var string|null
     */
    private $absolutePath;

    /**
     * @var array|null
     */
    private $preContext;

    /**
     * @var array|null
     */
    private $postContext;

    public function __construct($filename, $contextLine, $lineNumber)
    {
        $this->filename = $filename;
        $this->contextLine = $contextLine;
        $this->lineNumber = $lineNumber;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function getContextLine()
    {
        return $this->contextLine;
    }

    /**
     * @return int
     */
    public function getLineNumber()
    {
        return $this->lineNumber;
    }

    /**
     * @param null|string $absolutePath
     */
    public function setAbsolutePath($absolutePath)
    {
        $this->absolutePath = $absolutePath;
    }

    /**
     * @return null|string
     */
    public function getAbsolutePath()
    {
        return $this->absolutePath;
    }

    /**
     * @param array|null $preContext
     */
    public function setPreContext(array $preContext = null)
    {
        $this->preContext = $preContext;
    }

    /**
     * @return array|null
     */
    public function getPreContext()
    {
        return $this->preContext;
    }

    /**
     * @param array|null $postContext
     */
    public function setPostContext(array $postContext = null)
    {
        $this->postContext = $postContext;
    }

    /**
     * @return array|null
     */
    public function getPostContext()
    {
        return $this->postContext;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array_filter(array(
            'abs_path' => $this->getAbsolutePath(),
            'filename' => $this->getFilename(),
            'pre_context' => $this->getPreContext(),
            'context_line' => $this->getContextLine(),
            'lineno' => $this->getLineNumber(),
            'post_context' => $this->getPostContext(),
        ));
    }
}

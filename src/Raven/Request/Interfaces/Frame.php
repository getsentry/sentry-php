<?php

namespace Raven\Request\Interfaces;
use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class Frame implements ToArrayInterface
{
    /**
     * @var string|null
     */
    private $filename;

    /**
     * @var string|null
     */
    private $function;

    /**
     * @var string|null
     */
    private $module;

    /**
     * @var integer|null
     */
    private $lineNumber;

    /**
     * @var integer|null
     */
    private $columnNumber;

    /**
     * @var string|null
     */
    private $absolutePath;

    /**
     * @var string|null
     */
    private $contextLine;

    /**
     * @var array|null
     */
    private $preContext;

    /**
     * @var array|null
     */
    private $postContext;

    /**
     * @var boolean|null
     */
    private $inApp;

    /**
     * @var array|null
     */
    private $vars;

    public function __construct($filename = null, $function = null, $module = null)
    {
        if (null === $filename && null === $function && null === $module) {
            throw new \InvalidArgumentException('At least one of the filename, function or module arguments is required.');
        }

        $this->filename = $filename;
        $this->function = $function;
        $this->module = $module;
    }

    /**
     * @return null|string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return null|string
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * @return null|string
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param int|null $lineNumber
     */
    public function setLineNumber($lineNumber)
    {
        $this->lineNumber = $lineNumber;
    }

    /**
     * @return int|null
     */
    public function getLineNumber()
    {
        return $this->lineNumber;
    }

    /**
     * @param int|null $columnNumber
     */
    public function setColumnNumber($columnNumber)
    {
        $this->columnNumber = $columnNumber;
    }

    /**
     * @return int|null
     */
    public function getColumnNumber()
    {
        return $this->columnNumber;
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
     * @param null|string $contextLine
     */
    public function setContextLine($contextLine)
    {
        $this->contextLine = $contextLine;
    }

    /**
     * @return null|string
     */
    public function getContextLine()
    {
        return $this->contextLine;
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
     * @param bool|null $inApp
     */
    public function setInApp($inApp)
    {
        $this->inApp = $inApp;
    }

    /**
     * @return bool|null
     */
    public function getInApp()
    {
        return $this->inApp;
    }

    /**
     * @param array|null $vars
     */
    public function setVars(array $vars = null)
    {
        $this->vars = $vars;
    }

    /**
     * @return array|null
     */
    public function getVars()
    {
        return $this->vars;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array_filter(array(
            'abs_path' => $this->getAbsolutePath(),
            'filename' => $this->getFilename(),
            'function' => $this->getFunction(),
            'module' => $this->getModule(),
            'vars' => $this->getVars(),
            'pre_context' => $this->getPreContext(),
            'context_line' => $this->getContextLine(),
            'lineno' => $this->getLineNumber(),
            'in_app' => $this->getInApp(),
            'post_context' => $this->getPostContext(),
        ));
    }
}

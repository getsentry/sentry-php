<?php

namespace Raven\Request\Interfaces;
use Guzzle\Common\ToArrayInterface;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class SingleException implements ToArrayInterface
{
    /**
     * @var string
     */
    private $value;

    /**
     * @var string|null
     */
    private $type;

    /**
     * @var string|null
     */
    private $module;

    /**
     * @var StackTrace|null
     */
    private $stackTrace;

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param null|string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return null|string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param null|string $module
     */
    public function setModule($module)
    {
        $this->module = $module;
    }

    /**
     * @return null|string
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param null|StackTrace $stackTrace
     */
    public function setStackTrace(StackTrace $stackTrace = null)
    {
        $this->stackTrace = $stackTrace;
    }

    /**
     * @return null|StackTrace
     */
    public function getStackTrace()
    {
        return $this->stackTrace;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return array_filter(array(
            'type' => $this->getType(),
            'value' => $this->getValue(),
            'module' => $this->getModule(),
            'stacktrace' => null !== $this->getStackTrace() ? $this->getStackTrace()->toArray() : null,
        ));
    }
}

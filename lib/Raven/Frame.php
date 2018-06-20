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

/**
 * This class represents a single frame of a stacktrace.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Frame implements \JsonSerializable
{
    /**
     * @var string The name of the function being called
     */
    private $functionName;

    /**
     * @var string The file where the frame originated
     */
    private $file;

    /**
     * @var int The line at which the frame originated
     */
    private $line;

    /**
     * @var string[] A list of source code lines before the one where the frame
     *               originated
     */
    private $preContext;

    /**
     * @var string|null The source code written at the line number of the file that
     *               originated this frame
     */
    private $contextLine;

    /**
     * @var string[] A list of source code lines after the one where the frame
     *               originated
     */
    private $postContext;

    /**
     * @var bool Flag telling whether the frame is related to the execution of
     *           the relevant code in this stacktrace
     */
    private $inApp = false;

    /**
     * @var array A mapping of variables which were available within this
     *            frame (usually context-locals)
     */
    private $vars = [];

    /**
     * Initializes a new instance of this class using the provided information.
     *
     * @param string $functionName The name of the function being called
     * @param string $file         The file where the frame originated
     * @param int    $line         The line at which the frame originated
     */
    public function __construct($functionName, $file, $line)
    {
        $this->functionName = $functionName;
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * Gets the name of the function being called.
     *
     * @return string
     */
    public function getFunctionName()
    {
        return $this->functionName;
    }

    /**
     * Gets the file where the frame originated.
     *
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Gets the line at which the frame originated.
     *
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * Gets a list of source code lines before the one where the frame originated.
     *
     * @return string[]|null
     */
    public function getPreContext()
    {
        return $this->preContext;
    }

    /**
     * Sets a list of source code lines before the one where the frame originated.
     *
     * @param string[]|null $preContext The source code lines
     */
    public function setPreContext(array $preContext = null)
    {
        $this->preContext = $preContext;
    }

    /**
     * Gets the source code written at the line number of the file that originated
     * this frame.
     *
     * @return string|null
     */
    public function getContextLine()
    {
        return $this->contextLine;
    }

    /**
     * Sets the source code written at the line number of the file that originated
     * this frame.
     *
     * @param string|null $contextLine The source code line
     */
    public function setContextLine($contextLine)
    {
        $this->contextLine = $contextLine;
    }

    /**
     * Gets a list of source code lines after the one where the frame originated.
     *
     * @return string[]|null
     */
    public function getPostContext()
    {
        return $this->postContext;
    }

    /**
     * Sets a list of source code lines after the one where the frame originated.
     *
     * @param string[]|null $postContext The source code lines
     */
    public function setPostContext(array $postContext = null)
    {
        $this->postContext = $postContext;
    }

    /**
     * Gets whether the frame is related to the execution of the relevant code
     * in this stacktrace.
     *
     * @return bool
     */
    public function isInApp()
    {
        return $this->inApp;
    }

    /**
     * Sets whether the frame is related to the execution of the relevant code
     * in this stacktrace.
     *
     * @param bool $inApp flag indicating whether the frame is application-related
     */
    public function setIsInApp($inApp)
    {
        $this->inApp = (bool) $inApp;
    }

    /**
     * Gets a mapping of variables which were available within this frame
     * (usually context-locals).
     *
     * @return array
     */
    public function getVars()
    {
        return $this->vars;
    }

    /**
     * Sets a mapping of variables which were available within this frame
     * (usually context-locals).
     *
     * @param array $vars The variables
     */
    public function setVars(array $vars)
    {
        $this->vars = $vars;
    }

    /**
     * Returns an array representation of the data of this frame modeled according
     * to the specifications of the Sentry SDK Stacktrace Interface.
     *
     * @return array
     */
    public function toArray()
    {
        $result = [
            'function' => $this->functionName,
            'filename' => $this->file,
            'lineno' => $this->line,
            'in_app' => $this->inApp,
        ];

        if (null !== $this->preContext) {
            $result['pre_context'] = $this->preContext;
        }

        if (null !== $this->contextLine) {
            $result['context_line'] = $this->contextLine;
        }

        if (null !== $this->postContext) {
            $result['post_context'] = $this->postContext;
        }

        if (!empty($this->vars)) {
            $result['vars'] = $this->vars;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}

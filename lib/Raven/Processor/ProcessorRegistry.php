<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Processor;

/**
 * This registry contains all the processors that will be executed before an
 * event is sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ProcessorRegistry
{
    /**
     * @var array List of processors sorted by priority
     */
    private $processors = [];

    /**
     * @var array
     */
    private $sortedProcessors = [];

    /**
     * Registers the given processor.
     *
     * @param ProcessorInterface $processor The processor instance
     * @param int                $priority  The priority at which the processor must run
     */
    public function addProcessor(ProcessorInterface $processor, $priority = 0)
    {
        $this->processors[$priority][] = $processor;

        unset($this->sortedProcessors);
    }

    /**
     * Removes the given processor from the list of available ones.
     *
     * @param ProcessorInterface $processor The processor instance
     */
    public function removeProcessor(ProcessorInterface $processor)
    {
        foreach ($this->processors as $priority => $processors) {
            foreach ($processors as $key => $value) {
                if ($value === $processor) {
                    unset($this->processors[$priority][$key], $this->sortedProcessors);
                }
            }
        }
    }

    /**
     * Gets the processors sorted by priority.
     *
     * @return ProcessorInterface[]
     */
    public function getProcessors()
    {
        if (empty($this->processors)) {
            return [];
        }

        if (empty($this->sortedProcessors)) {
            krsort($this->processors);

            $this->sortedProcessors = array_merge(...$this->processors);
        }

        return $this->sortedProcessors;
    }
}

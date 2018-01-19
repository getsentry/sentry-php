<?php

namespace Raven\Tests\Processor;

use PHPUnit\Framework\TestCase;
use Raven\Processor\ProcessorInterface;
use Raven\Processor\ProcessorRegistry;

class ProcessorRegistryTest extends TestCase
{
    /**
     * @var ProcessorRegistry
     */
    protected $processorRegistry;

    protected function setUp()
    {
        $this->processorRegistry = new ProcessorRegistry();
    }

    public function testAddProcessor()
    {
        /** @var ProcessorInterface $processor1 */
        $processor1 = $this->createMock(ProcessorInterface::class);

        /** @var ProcessorInterface $processor2 */
        $processor2 = $this->createMock(ProcessorInterface::class);

        /** @var ProcessorInterface $processor3 */
        $processor3 = $this->createMock(ProcessorInterface::class);

        $this->processorRegistry->addProcessor($processor1, -10);
        $this->processorRegistry->addProcessor($processor2, 10);
        $this->processorRegistry->addProcessor($processor3);

        $processors = $this->processorRegistry->getProcessors();

        $this->assertCount(3, $processors);
        $this->assertSame($processor2, $processors[0]);
        $this->assertSame($processor3, $processors[1]);
        $this->assertSame($processor1, $processors[2]);
    }

    public function testRemoveProcessor()
    {
        /** @var ProcessorInterface $processor1 */
        $processor1 = $this->createMock(ProcessorInterface::class);

        /** @var ProcessorInterface $processor2 */
        $processor2 = $this->createMock(ProcessorInterface::class);

        /** @var ProcessorInterface $processor3 */
        $processor3 = $this->createMock(ProcessorInterface::class);

        $this->processorRegistry->addProcessor($processor1, -10);
        $this->processorRegistry->addProcessor($processor2, 10);
        $this->processorRegistry->addProcessor($processor3);

        $this->assertCount(3, $this->processorRegistry->getProcessors());

        $this->processorRegistry->removeProcessor($processor1);

        $processors = $this->processorRegistry->getProcessors();

        $this->assertCount(2, $processors);
        $this->assertSame($processor2, $processors[0]);
        $this->assertSame($processor3, $processors[1]);
    }
}

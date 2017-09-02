<?php

namespace Raven\Tests;

use PHPUnit\Framework\TestCase;
use Raven\Context;

class ContextTest extends TestCase
{
    public function testSetTag()
    {
        $context = new Context();

        $context->setTag('foo', 'bar');
        $context->setTag('foo', 'baz');

        $this->assertEquals(['foo' => 'baz'], $context->getTags());
    }

    public function testMergeUserData()
    {
        $context = new Context();

        $context->mergeUserData(['foo' => 'bar']);
        $context->mergeUserData(['baz' => 'bar']);

        $this->assertEquals(['foo' => 'bar', 'baz' => 'bar'], $context->getUserData());
    }

    public function testMergeUserDataWithSameKey()
    {
        $context = new Context();

        $context->mergeUserData(['foo' => 'bar']);
        $context->mergeUserData(['foo' => 'baz']);

        $this->assertEquals(['foo' => 'baz'], $context->getUserData());
    }

    public function testSetUserData()
    {
        $context = new Context();

        $context->setUserData(['foo' => 'bar']);
        $context->setUserData(['bar' => 'baz']);

        $this->assertEquals(['bar' => 'baz'], $context->getUserData());
    }

    public function testMergeExtraData()
    {
        $context = new Context();

        $context->mergeExtraData(['foo' => 'bar']);
        $context->mergeExtraData(['baz' => 'bar']);

        $this->assertEquals(['foo' => 'bar', 'baz' => 'bar'], $context->getExtraData());
    }

    public function testMergeExtraDataWithSameKey()
    {
        $context = new Context();

        $context->mergeExtraData(['foo' => 'bar']);
        $context->mergeExtraData(['foo' => 'baz']);

        $this->assertEquals(['foo' => 'baz'], $context->getExtraData());
    }
}

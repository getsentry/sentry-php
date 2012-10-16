<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_SerializerTestObject
{
    private $foo = 'bar';
}

class Raven_Tests_SerializerTest extends PHPUnit_Framework_TestCase
{
    public function testToStringSanitizesArrays()
    {
        $input = array(1, 2, 3);
        $result = Raven_Serializer::serialize($input);
        $this->assertEquals($result, array('1', '2', '3'));
    }

    public function testToStringSanitizesObjectsToStrings()
    {
        $input = new Raven_StacktraceTestObject();
        $result = Raven_Serializer::serialize($input);
        $this->assertEquals($result, 'Object Raven_StacktraceTestObject');
    }
}

<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\HttpClient\Stream;

use Psr\Http\Message\StreamInterface;
use Raven\HttpClient\Stream\Base64EncodingStream;
use Zend\Diactoros\Stream;

class Base64EncodingStreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getSizeDataProvider
     */
    public function testGetSize($content, $decodedSize, $encodedSize)
    {
        /** @var StreamInterface|\PHPUnit_Framework_MockObject_MockObject $stream */
        $stream = $this->getMockBuilder(StreamInterface::class)
            ->getMock();

        $stream->expects($this->once())
            ->method('getSize')
            ->willReturn($decodedSize);

        $encodingStream = new Base64EncodingStream($stream);

        $stream->write($content);

        $this->assertSame($encodedSize, $encodingStream->getSize());
    }

    public function getSizeDataProvider()
    {
        return [
            ['', null, null],
            ['foo', 3, 4],
            ['foo bar', 7, 12],
        ];
    }

    /**
     * @dataProvider readDataProvider
     */
    public function testRead($decoded, $encoded)
    {
        $stream = new Stream('php://memory', 'r+');
        $encodingStream = new Base64EncodingStream($stream);

        $stream->write($decoded);
        $stream->rewind();

        $this->assertSame($encoded, $encodingStream->getContents());
    }

    public function readDataProvider()
    {
        return [
            ['', ''],
            ['foo', 'Zm9v'],
            ['foo bar', 'Zm9vIGJhcg=='],
        ];
    }
}
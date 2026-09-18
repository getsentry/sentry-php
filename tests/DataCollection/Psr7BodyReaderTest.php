<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Sentry\DataCollection\Psr7BodyReader;

final class Psr7BodyReaderTest extends TestCase
{
    public function testBodyIsReadAndOriginalPositionIsRestored(): void
    {
        $stream = Utils::streamFor('request body');
        $stream->seek(4);

        $this->assertSame('request body', Psr7BodyReader::read($stream, 100));
        $this->assertSame(4, $stream->tell());
        $this->assertSame('est body', $stream->getContents());
    }

    public function testNonSeekableBodyIsNotRead(): void
    {
        $stream = new NoSeekStream(Utils::streamFor('request body'));

        $this->assertNull(Psr7BodyReader::read($stream, 100));
        $this->assertSame('request body', $stream->getContents());
    }

    public function testOversizedBodyIsNotReturnedAndOriginalPositionIsRestored(): void
    {
        $stream = Utils::streamFor('request body');
        $stream->seek(4);

        $this->assertNull(Psr7BodyReader::read($stream, 5));
        $this->assertSame(4, $stream->tell());
    }

    public function testReadStopsAfterExceedingLimit(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(3);
        $stream->method('eof')->willReturn(false);
        $stream->expects($this->once())->method('read')->with(6)->willReturn('abcdef');
        $stream->expects($this->once())->method('seek')->with(3);

        $this->assertNull(Psr7BodyReader::read($stream, 5));
    }

    public function testBodyAtLimitIsReturned(): void
    {
        $this->assertSame('body', Psr7BodyReader::read(Utils::streamFor('body'), 4));
    }

    public function testEmptyBodyIsReturned(): void
    {
        $this->assertSame('', Psr7BodyReader::read(Utils::streamFor(''), 4));
    }

    public function testDisabledBodyIsNotInspected(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->never())->method('isReadable');
        $stream->expects($this->never())->method('isSeekable');

        $this->assertNull(Psr7BodyReader::read($stream, 0));
    }

    public function testUnreadableBodyIsNotRead(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(false);
        $stream->expects($this->never())->method('read');

        $this->assertNull(Psr7BodyReader::read($stream, 100));
    }

    public function testReadFailureRestoresOriginalPosition(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(3);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willThrowException(new \RuntimeException('Cannot read'));
        $stream->expects($this->once())->method('seek')->with(3);

        $this->assertNull(Psr7BodyReader::read($stream, 100));
    }

    public function testRestoreFailureOmitsBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(3);
        $stream->method('eof')->willReturn(true);
        $stream->expects($this->once())->method('seek')->with(3)->willThrowException(new \RuntimeException('Cannot seek'));

        $this->assertNull(Psr7BodyReader::read($stream, 100));
    }
}

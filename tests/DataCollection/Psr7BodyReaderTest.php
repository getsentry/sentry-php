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
    /**
     * @dataProvider bodyReadLimitProvider
     */
    public function testBodyIsReadAndOriginalPositionIsRestored(int $limit): void
    {
        $stream = Utils::streamFor('request body');
        $stream->seek(4);

        $this->assertSame('request body', Psr7BodyReader::read($stream, $limit));
        $this->assertSame(4, $stream->tell());
        $this->assertSame('est body', $stream->getContents());
    }

    /**
     * @dataProvider bodyReadLimitProvider
     */
    public function testNonSeekableBodyIsNotRead(int $limit): void
    {
        $stream = new NoSeekStream(Utils::streamFor('request body'));

        $this->assertNull(Psr7BodyReader::read($stream, $limit));
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

    public function testTruncatedReadStopsAtLimitAndRestoresOriginalPosition(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(3);
        $stream->method('eof')->willReturn(false);
        $stream->expects($this->once())->method('read')->with(5)->willReturn('abcde');
        $stream->expects($this->once())->method('seek')->with(3);

        $this->assertSame('abcde', Psr7BodyReader::read($stream, 5, true));
    }

    public function testBodyAtLimitIsReturned(): void
    {
        $this->assertSame('body', Psr7BodyReader::read(Utils::streamFor('body'), 4));
    }

    /**
     * @dataProvider bodyReadLimitProvider
     */
    public function testEmptyBodyIsReturned(int $limit): void
    {
        $this->assertSame('', Psr7BodyReader::read(Utils::streamFor(''), $limit));
    }

    /**
     * @dataProvider disabledBodyReadLimitProvider
     */
    public function testDisabledBodyIsNotInspected(int $limit): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->never())->method('isReadable');
        $stream->expects($this->never())->method('isSeekable');

        $this->assertNull(Psr7BodyReader::read($stream, $limit));
    }

    /**
     * @dataProvider bodyReadLimitProvider
     */
    public function testUnreadableBodyIsNotRead(int $limit): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(false);
        $stream->expects($this->never())->method('read');

        $this->assertNull(Psr7BodyReader::read($stream, $limit));
    }

    /**
     * @dataProvider bodyReadLimitProvider
     */
    public function testReadFailureRestoresOriginalPosition(int $limit): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(3);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->willThrowException(new \RuntimeException('Cannot read'));
        $stream->expects($this->once())->method('seek')->with(3);

        $this->assertNull(Psr7BodyReader::read($stream, $limit));
    }

    /**
     * @dataProvider bodyReadLimitProvider
     */
    public function testRestoreFailureOmitsBody(int $limit): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(3);
        $stream->method('eof')->willReturn(true);
        $stream->expects($this->once())->method('seek')->with(3)->willThrowException(new \RuntimeException('Cannot seek'));

        $this->assertNull(Psr7BodyReader::read($stream, $limit));
    }

    public function testUnlimitedBodyIsReadAndOriginalPositionIsRestored(): void
    {
        $body = str_repeat('a', 100001);
        $stream = Utils::streamFor($body);
        $stream->seek(4);

        $this->assertSame($body, Psr7BodyReader::read($stream, -1));
        $this->assertSame(4, $stream->tell());
    }

    public function testUnlimitedReadStopsWhenStreamReturnsNoData(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(3);
        $stream->method('eof')->willReturn(false);
        $stream->expects($this->once())->method('read')->willReturn('');
        $stream->expects($this->once())->method('seek')->with(3);

        $this->assertSame('', Psr7BodyReader::read($stream, -1));
    }

    public function bodyReadLimitProvider(): \Generator
    {
        yield 'bounded' => [100];
        yield 'unlimited' => [-1];
    }

    public function disabledBodyReadLimitProvider(): \Generator
    {
        yield 'zero' => [0];
        yield 'invalid negative limit' => [-2];
    }
}

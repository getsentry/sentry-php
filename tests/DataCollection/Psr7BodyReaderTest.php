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

    public function testDisabledBodyIsNotInspected(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->never())->method('isReadable');
        $stream->expects($this->never())->method('isSeekable');

        $this->assertNull(Psr7BodyReader::read($stream, 0));
    }
}

<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Attachment;

final class AttachmentTest extends TestCase
{
    /**
     * @dataProvider constructorDataProvider
     */
    public function testConstructor(array $constructorArgs, string $expectedFilename, string $expectedContentType, string $expectedData): void
    {
        $attachment = new Attachment(...$constructorArgs);

        $this->assertSame($expectedFilename, $attachment->getFilename());
        $this->assertSame($expectedContentType, $attachment->getContentType());
        $this->assertSame($expectedData, $attachment->getData());
    }

    public function constructorDataProvider(): \Generator
    {
        yield [
            ['data', 'filename.ext'],
            'filename.ext',
            'application/octet-stream',
            'data',
        ];

        yield [
            ['data', 'filename.ext', 'application/json'],
            'filename.ext',
            'application/json',
            'data',
        ];
    }

    public function testFromFile(): void
    {
        $attachment = Attachment::fromFile(__DIR__ . '/data/attachment.txt', 'text/plain');

        $this->assertSame('attachment.txt', $attachment->getFilename());
        $this->assertSame('text/plain', $attachment->getContentType());
        $this->assertSame("Attachment.\n", $attachment->getData());
    }
}

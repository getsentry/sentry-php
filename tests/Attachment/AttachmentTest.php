<?php

declare(strict_types=1);

namespace Sentry\Tests\Attachment;

use PHPUnit\Framework\TestCase;
use Sentry\Attachment\Attachment;

class AttachmentTest extends TestCase
{
    public function testFileAttachment(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'attachment.txt');
        file_put_contents($file, 'This is a temp attachment');
        $attachment = Attachment::fromFile($file);
        $this->assertEquals(25, $attachment->getSize());
        $this->assertEquals('This is a temp attachment', $attachment->getData());
        $this->assertStringStartsWith('attachment.txt', $attachment->getFilename());
        $this->assertEquals('application/octet-stream', $attachment->getContentType());
    }

    public function testEmptyFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'attachment.txt');
        $attachment = Attachment::fromFile($file);
        $this->assertEquals(0, $attachment->getSize());
        $this->assertEquals('', $attachment->getData());
    }

    public function testFileDoesNotExist(): void
    {
        $attachment = Attachment::fromFile('this/does/not/exist');
        $this->assertNull($attachment->getSize());
        $this->assertNull($attachment->getData());
    }

    public function testByteAttachment(): void
    {
        $attachment = Attachment::fromBytes('test', 'ExampleDataThatShouldNotBeAFile');
        $this->assertEquals(31, $attachment->getSize());
        $this->assertEquals('ExampleDataThatShouldNotBeAFile', $attachment->getData());
        $this->assertEquals('test', $attachment->getFilename());
        $this->assertEquals('application/octet-stream', $attachment->getContentType());
    }

    public function testEmptyBytes(): void
    {
        $attachment = Attachment::fromBytes('test', '');
        $this->assertEquals(0, $attachment->getSize());
        $this->assertEquals('', $attachment->getData());
    }
}

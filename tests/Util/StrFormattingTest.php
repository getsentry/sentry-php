<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Util\Str;

/**
 * Demo for the mutation testing job, remove before merging: repeats a test of StrTest in another file.
 */
final class StrFormattingTest extends TestCase
{
    public function testFormatsValue(): void
    {
        $this->assertSame('Hello world', Str::vsprintfOrNull('Hello %s', ['world']));
    }
}

<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Util\SentryUid;

final class SentryUidTest extends TestCase
{
    public function testGenerate(): void
    {
        $result = SentryUid::generate();
        $pattern = '/^[0-9a-f]{8}[0-9a-f]{4}4[0-9a-f]{3}[89ab][0-9a-f]{3}[0-9a-f]{12}$/';
        $match = (bool) preg_match($pattern, $result);
        $this->assertTrue($match);
    }
}

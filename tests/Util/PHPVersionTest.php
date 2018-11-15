<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Util\PHPVersion;

final class PHPVersionTest extends TestCase
{
    /**
     * @dataProvider versionProvider
     */
    public function testGetParsed(string $rawVersion, string $expectedVersion): void
    {
        $this->assertEquals($expectedVersion, PHPVersion::parseVersion($rawVersion));
    }

    public function versionProvider(): array
    {
        return [
            ['1.2.3', '1.2.3'],
            ['1.2.3-1+ubuntu17.04.1+deb.sury.org+1', '1.2.3'],
            ['1.2.3-beta3-1+ubuntu17.04.1+deb.sury.org+1', '1.2.3-beta3'],
            ['1.2.3-beta5-dev-1+ubuntu17.04.1+deb.sury.org+1', '1.2.3-beta5-dev'],
            ['1.2.3-rc-9-1+ubuntu17.04.1+deb.sury.org+1', '1.2.3-rc-9'],
            ['1.2.3-2~ubuntu16.04.1+deb.sury.org+1', '1.2.3'],
            ['1.2.3-beta1-dev', '1.2.3-beta1-dev'],
            ['1.2.3-rc10', '1.2.3-rc10'],
            ['1.2.3-RC10', '1.2.3-RC10'],
            ['1.2.3-rc2-dev', '1.2.3-rc2-dev'],
            ['1.2.3-beta-2-dev', '1.2.3-beta-2-dev'],
            ['1.2.3-beta2', '1.2.3-beta2'],
            ['1.2.3-beta-9', '1.2.3-beta-9'],
        ];
    }
}

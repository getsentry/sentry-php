<?php

namespace Raven\Tests\Util;

use PHPUnit\Framework\TestCase;
use Raven\Util\PHPVersion;

class PHPVersionTest extends TestCase
{
    /**
     * @dataProvider versionProvider
     * @param $expected
     * @param $rawVersion
     */
    public function testGetParsed($expected, $rawVersion)
    {
        $this->assertSame($expected, PHPVersion::getParsed($rawVersion));
    }

    public function versionProvider()
    {
        $baseVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
        $phpExtraVersions = [
            '' => $baseVersion,
            '-1+ubuntu17.04.1+deb.sury.org+1' => $baseVersion,
            '-beta3-1+ubuntu17.04.1+deb.sury.org+1' => "{$baseVersion}-beta3",
            '-beta5-dev-1+ubuntu17.04.1+deb.sury.org+1' => "{$baseVersion}-beta5-dev",
            '-rc-9-1+ubuntu17.04.1+deb.sury.org+1' => "{$baseVersion}-rc-9",
            '-2~ubuntu16.04.1+deb.sury.org+1' => $baseVersion,
            '-beta1-dev' => "{$baseVersion}-beta1-dev",
            '-rc10' => "{$baseVersion}-rc10",
            '-RC10' => "{$baseVersion}-RC10",
            '-rc2-dev' => "{$baseVersion}-rc2-dev",
            '-beta-2-dev' => "{$baseVersion}-beta-2-dev",
            '-beta2' => "{$baseVersion}-beta2",
            '-beta-9' => "{$baseVersion}-beta-9",
        ];

        foreach ($phpExtraVersions as $fullVersion => $parsedVersion) {
            yield [$parsedVersion, $baseVersion . $fullVersion];
        }
    }
}

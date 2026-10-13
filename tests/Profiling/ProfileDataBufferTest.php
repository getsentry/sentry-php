<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiling;

use PHPUnit\Framework\TestCase;
use Sentry\Options;
use Sentry\Profiling\ProfileData;
use Sentry\Profiling\ProfileDataBuffer;
use Sentry\Profiling\ProfileFrame;
use Sentry\Profiling\ProfileSample;

final class ProfileDataBufferTest extends TestCase
{
    public function testTakeProfileDataReturnsEmptyDataForEmptyBuffer(): void
    {
        $buffer = new ProfileDataBuffer();

        $this->assertEquals(new ProfileData([], [], []), $buffer->takeProfileData());
    }

    /**
     * @dataProvider addDataProvider
     */
    public function testAdd(array $excimerLog, ProfileData $expectedData, ?Options $options = null): void
    {
        $buffer = new ProfileDataBuffer($options);

        foreach ($excimerLog as $entry) {
            $buffer->add($entry);
        }

        $this->assertEquals($expectedData, $buffer->takeProfileData());
    }

    public static function addDataProvider(): \Generator
    {
        $excimerLog = [
            [
                'trace' => [
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                ],
                'timestamp' => 0.001,
            ],
            [
                'trace' => [
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                ],
                'timestamp' => 0.002,
            ],
            [
                'trace' => [
                    [
                        'file' => '/var/www/html/function.php',
                        'line' => 7,
                        'class' => 'Function',
                        'function' => 'doStuff',
                    ],
                    [
                        'file' => '/var/www/html/closure.php',
                        'line' => 3,
                        'function' => '{closure}',
                    ],
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                ],
                'timestamp' => 0.003,
            ],
        ];

        yield 'Frames and stacks are deduplicated' => [
            $excimerLog,
            new ProfileData(
                [
                    new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', '/var/www/html/index.php', null, 42),
                    new ProfileFrame('/var/www/html/function.php', '/var/www/html/function.php', 'Function::doStuff', 'Function', 7),
                    new ProfileFrame('/var/www/html/closure.php', '/var/www/html/closure.php', '{closure}', null, 3),
                ],
                [
                    [0],
                    [1, 2, 0],
                ],
                [
                    new ProfileSample(0, 0.001),
                    new ProfileSample(0, 0.002),
                    new ProfileSample(1, 0.003),
                ]
            ),
        ];

        yield 'Prefixes are stripped from the filename' => [
            $excimerLog,
            new ProfileData(
                [
                    new ProfileFrame('/var/www/html/index.php', '/index.php', '/index.php', null, 42),
                    new ProfileFrame('/var/www/html/function.php', '/function.php', 'Function::doStuff', 'Function', 7),
                    new ProfileFrame('/var/www/html/closure.php', '/closure.php', '{closure}', null, 3),
                ],
                [
                    [0],
                    [1, 2, 0],
                ],
                [
                    new ProfileSample(0, 0.001),
                    new ProfileSample(0, 0.002),
                    new ProfileSample(1, 0.003),
                ]
            ),
            new Options([
                'prefixes' => ['/var/www/html'],
            ]),
        ];

        yield 'Frames are deduplicated by file and line' => [
            [
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/index.php',
                            'line' => 42,
                            'function' => 'first',
                        ],
                    ],
                    'timestamp' => 0.001,
                ],
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/index.php',
                            'line' => 43,
                            'function' => 'first',
                        ],
                    ],
                    'timestamp' => 0.002,
                ],
            ],
            new ProfileData(
                [
                    new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', 'first', null, 42),
                    new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', 'first', null, 43),
                ],
                [
                    [0],
                    [1],
                ],
                [
                    new ProfileSample(0, 0.001),
                    new ProfileSample(1, 0.002),
                ]
            ),
        ];
    }
}

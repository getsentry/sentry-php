<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiling;

use PHPUnit\Framework\TestCase;
use Sentry\Options;
use Sentry\Profiling\ExcimerLogConverter;
use Sentry\Profiling\ProfileData;
use Sentry\Profiling\ProfileFrame;
use Sentry\Profiling\ProfileSample;

final class ExcimerLogConverterTest extends TestCase
{
    public function testConvertReturnsEmptyDataForEmptyLog(): void
    {
        $this->assertEquals(new ProfileData([], [], []), ExcimerLogConverter::convert([]));
    }

    /**
     * @dataProvider convertDataProvider
     */
    public function testConvert(array $excimerLog, ProfileData $expectedData, ?Options $options = null): void
    {
        $this->assertEquals($expectedData, ExcimerLogConverter::convert($excimerLog, $options));
    }

    public static function convertDataProvider(): \Generator
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

        yield 'Prefixes are stripped from the file in the name of a closure' => [
            [
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/routes.php',
                            'line' => 13,
                            'function' => '{closure:/var/www/html/routes.php:12}',
                        ],
                    ],
                    'timestamp' => 0.001,
                ],
            ],
            new ProfileData(
                [
                    new ProfileFrame('/var/www/html/routes.php', '/routes.php', '{closure:/routes.php:12}', null, 13),
                ],
                [
                    [0],
                ],
                [
                    new ProfileSample(0, 0.001),
                ]
            ),
            new Options([
                'prefixes' => ['/var/www/html'],
            ]),
        ];

        yield 'Samples recorded for several ticks are moved to the first tick' => [
            [
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/index.php',
                            'line' => 42,
                        ],
                    ],
                    'timestamp' => 0.03,
                    'event_count' => 3,
                ],
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/index.php',
                            'line' => 42,
                        ],
                    ],
                    'timestamp' => 0.04,
                ],
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/index.php',
                            'line' => 43,
                        ],
                    ],
                    'timestamp' => 0.24,
                    'event_count' => 20,
                ],
            ],
            new ProfileData(
                [
                    new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', '/var/www/html/index.php', null, 42),
                    new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', '/var/www/html/index.php', null, 43),
                ],
                [
                    [0],
                    [1],
                ],
                [
                    // Without a previous sample, the first tick is unknown
                    new ProfileSample(0, 0.03),
                    new ProfileSample(0, 0.04),
                    new ProfileSample(1, 0.05),
                ]
            ),
        ];

        $frameA = [
            'file' => '/var/www/html/a.php',
            'line' => 1,
            'function' => 'a',
        ];
        $frameB = [
            'file' => '/var/www/html/b.php',
            'line' => 2,
            'function' => 'b',
        ];

        yield 'Stacks are only deduplicated if they contain the same frames in the same order' => [
            [
                [
                    'trace' => [$frameA],
                    'timestamp' => 0.001,
                ],
                [
                    'trace' => [$frameA, $frameA],
                    'timestamp' => 0.002,
                ],
                [
                    'trace' => [$frameB, $frameA],
                    'timestamp' => 0.003,
                ],
                [
                    'trace' => [$frameA, $frameB],
                    'timestamp' => 0.004,
                ],
                [
                    'trace' => [$frameA],
                    'timestamp' => 0.005,
                ],
            ],
            new ProfileData(
                [
                    new ProfileFrame('/var/www/html/a.php', '/var/www/html/a.php', 'a', null, 1),
                    new ProfileFrame('/var/www/html/b.php', '/var/www/html/b.php', 'b', null, 2),
                ],
                [
                    [0],
                    [0, 0],
                    [1, 0],
                    [0, 1],
                ],
                [
                    new ProfileSample(0, 0.001),
                    new ProfileSample(1, 0.002),
                    new ProfileSample(2, 0.003),
                    new ProfileSample(3, 0.004),
                    new ProfileSample(0, 0.005),
                ]
            ),
        ];
    }
}

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
            $buffer->add($entry, '0', 'main');
        }

        $this->assertEquals($expectedData, $buffer->takeProfileData());
    }

    public function testAddAttributesSamplesToThreads(): void
    {
        $entry = [
            'trace' => [
                [
                    'file' => '/var/www/html/index.php',
                    'line' => 42,
                ],
            ],
            'timestamp' => 0.001,
        ];

        $buffer = new ProfileDataBuffer();
        $buffer->add($entry, '1', 'coroutine 1');
        $buffer->add(['timestamp' => 0.002] + $entry, '2', 'coroutine 2');
        $buffer->add(['timestamp' => 0.003] + $entry, '1', 'coroutine 1');

        $this->assertEquals(new ProfileData(
            [
                new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', '/var/www/html/index.php', null, 42),
            ],
            [
                [0],
            ],
            [
                new ProfileSample(0, 0.001, '1', 'coroutine 1'),
                new ProfileSample(0, 0.002, '2', 'coroutine 2'),
                new ProfileSample(0, 0.003, '1', 'coroutine 1'),
            ]
        ), $buffer->takeProfileData());
    }

    public function testTakeProfileDataEmptiesTheBuffer(): void
    {
        $entry = [
            'trace' => [
                [
                    'file' => '/var/www/html/index.php',
                    'line' => 42,
                ],
            ],
            'timestamp' => 0.001,
        ];

        $buffer = new ProfileDataBuffer();
        $buffer->add($entry, '0', 'main');

        $this->assertSame(0.001, $buffer->getStartTimestamp());
        $this->assertEquals([new ProfileSample(0, 0.001, '0', 'main')], $buffer->takeProfileData()->getSamples());
        $this->assertSame(0, $buffer->getSampleCount());
        $this->assertNull($buffer->getStartTimestamp());

        $buffer->add(['timestamp' => 0.002] + $entry, '1', 'coroutine 1');

        $this->assertSame(0.002, $buffer->getStartTimestamp());
        $this->assertEquals([new ProfileSample(0, 0.002, '1', 'coroutine 1')], $buffer->takeProfileData()->getSamples());
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
                    new ProfileSample(0, 0.001, '0', 'main'),
                    new ProfileSample(0, 0.002, '0', 'main'),
                    new ProfileSample(1, 0.003, '0', 'main'),
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
                    new ProfileSample(0, 0.001, '0', 'main'),
                    new ProfileSample(0, 0.002, '0', 'main'),
                    new ProfileSample(1, 0.003, '0', 'main'),
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
                    new ProfileSample(0, 0.001, '0', 'main'),
                    new ProfileSample(1, 0.002, '0', 'main'),
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
                    new ProfileSample(0, 0.001, '0', 'main'),
                    new ProfileSample(1, 0.002, '0', 'main'),
                    new ProfileSample(2, 0.003, '0', 'main'),
                    new ProfileSample(3, 0.004, '0', 'main'),
                    new ProfileSample(0, 0.005, '0', 'main'),
                ]
            ),
        ];

        yield 'Closures on the same line as their caller are not merged into the caller frame' => [
            [
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/index.php',
                            'line' => 10,
                            'function' => '{closure}',
                            'closure_line' => 10,
                        ],
                        [
                            'file' => '/var/www/html/index.php',
                            'line' => 10,
                            'class' => 'App\\Controller',
                            'function' => 'index',
                        ],
                    ],
                    'timestamp' => 0.001,
                ],
            ],
            new ProfileData(
                [
                    new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', '{closure}', null, 10),
                    new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', 'App\\Controller::index', 'App\\Controller', 10),
                ],
                [
                    [0, 1],
                ],
                [
                    new ProfileSample(0, 0.001, '0', 'main'),
                ]
            ),
        ];

        yield 'Trait methods used by different classes are not merged' => [
            [
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/ForwardsCalls.php',
                            'line' => 5,
                            'class' => 'App\\Foo',
                            'function' => 'forwardCall',
                        ],
                    ],
                    'timestamp' => 0.001,
                ],
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/ForwardsCalls.php',
                            'line' => 5,
                            'class' => 'App\\Bar',
                            'function' => 'forwardCall',
                        ],
                    ],
                    'timestamp' => 0.002,
                ],
            ],
            new ProfileData(
                [
                    new ProfileFrame('/var/www/html/ForwardsCalls.php', '/var/www/html/ForwardsCalls.php', 'App\\Foo::forwardCall', 'App\\Foo', 5),
                    new ProfileFrame('/var/www/html/ForwardsCalls.php', '/var/www/html/ForwardsCalls.php', 'App\\Bar::forwardCall', 'App\\Bar', 5),
                ],
                [
                    [0],
                    [1],
                ],
                [
                    new ProfileSample(0, 0.001, '0', 'main'),
                    new ProfileSample(1, 0.002, '0', 'main'),
                ]
            ),
        ];
    }
}

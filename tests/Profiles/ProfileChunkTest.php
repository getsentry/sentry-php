<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiles;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Options;
use Sentry\Profiles\ProfileChunk;

final class ProfileChunkTest extends TestCase
{
    /**
     * @dataProvider formattedDataDataProvider
     */
    public function testGetFormattedData(Event $event, array $excimerLogs, $expectedData, ?Options $options = null): void
    {
        $profileChunk = new ProfileChunk($options);
        // 2025-06-08T09:41:00Z
        $profileChunk->setStartTimeStamp(1749368460.0000);
        $profileChunk->setProfilerId('550e8400e29b41d4a716446655440000');
        $profileChunk->setChunkId('a1b2c3d4e5f64a7b8c9d0e1f2a3b4c5d');
        $profileChunk->setExcimerLogs($excimerLogs);

        $this->assertEquals($expectedData, $profileChunk->getFormattedData($event));
    }

    public static function formattedDataDataProvider(): \Generator
    {
        $event = Event::createProfileChunk();
        $event->setRelease('1.0.0');
        $event->setEnvironment('dev');
        $event->setSdkIdentifier('sentry.php');
        $event->setSdkVersion('4.12.0');

        $excimerLogData = [
            [
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
                            'file' => '/var/www/html/index.php',
                            'line' => 42,
                        ],
                        [
                            'class' => 'Function',
                            'function' => 'doStuff',
                            'file' => '/var/www/html/function.php',
                            'line' => 84,
                        ],
                    ],
                    'timestamp' => 0.003,
                ],
                [
                    'trace' => [
                        [
                            'file' => '/var/www/html/index.php',
                            'line' => 42,
                        ],
                        [
                            'class' => 'Function',
                            'function' => 'doStuff',
                            'file' => '/var/www/html/function.php',
                            'line' => 84,
                        ],
                        [
                            'class' => 'Class\Something',
                            'function' => 'run',
                            'file' => '/var/www/html/class.php',
                            'line' => 42,
                        ],
                        [
                            'function' => '{closure}',
                            'file' => '/var/www/html/index.php',
                            'line' => 126,
                        ],
                    ],
                    'timestamp' => 0.004,
                ],
            ],
        ];

        yield 'Basic profiling data' => [
            $event,
            $excimerLogData,
            [
                'profiler_id' => '550e8400e29b41d4a716446655440000',
                'chunk_id' => 'a1b2c3d4e5f64a7b8c9d0e1f2a3b4c5d',
                'platform' => 'php',
                'release' => '1.0.0',
                'environment' => 'dev',
                'version' => '2',
                'profile' => [
                    'frames' => [
                        [
                            'filename' => '/var/www/html/index.php',
                            'abs_path' => '/var/www/html/index.php',
                            'module' => null,
                            'function' => '/var/www/html/index.php',
                            'lineno' => 42,
                        ],
                        [
                            'filename' => '/var/www/html/function.php',
                            'abs_path' => '/var/www/html/function.php',
                            'module' => 'Function',
                            'function' => 'Function::doStuff',
                            'lineno' => 84,
                        ],
                        [
                            'filename' => '/var/www/html/class.php',
                            'abs_path' => '/var/www/html/class.php',
                            'module' => 'Class\Something',
                            'function' => 'Class\Something::run',
                            'lineno' => 42,
                        ],
                        [
                            'filename' => '/var/www/html/index.php',
                            'abs_path' => '/var/www/html/index.php',
                            'module' => null,
                            'function' => '{closure}',
                            'lineno' => 126,
                        ],
                    ],
                    'samples' => [
                        [
                            'stack_id' => 0,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.001,
                        ],
                        [
                            'stack_id' => 0,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.002,
                        ],
                        [
                            'stack_id' => 1,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.003,
                        ],
                        [
                            'stack_id' => 2,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.004,
                        ],
                    ],
                    'stacks' => [
                        [
                            0,
                        ],
                        [
                            0,
                            1,
                        ],
                        [
                            0,
                            1,
                            2,
                            3,
                        ],
                    ],
                    'thread_metadata' => (object) [
                        '0' => [
                            'name' => 'main',
                        ],
                    ],
                ],
                'client_sdk' => [
                    'name' => 'sentry.php',
                    'version' => '4.12.0',
                ],
            ],
        ];

        yield 'With prefix stripping options' => [
            $event,
            $excimerLogData,
            [
                'profiler_id' => '550e8400e29b41d4a716446655440000',
                'chunk_id' => 'a1b2c3d4e5f64a7b8c9d0e1f2a3b4c5d',
                'platform' => 'php',
                'release' => '1.0.0',
                'environment' => 'dev',
                'version' => '2',
                'profile' => [
                    'frames' => [
                        [
                            'filename' => '/index.php',
                            'abs_path' => '/var/www/html/index.php',
                            'module' => null,
                            'function' => '/index.php',
                            'lineno' => 42,
                        ],
                        [
                            'filename' => '/function.php',
                            'abs_path' => '/var/www/html/function.php',
                            'module' => 'Function',
                            'function' => 'Function::doStuff',
                            'lineno' => 84,
                        ],
                        [
                            'filename' => '/class.php',
                            'abs_path' => '/var/www/html/class.php',
                            'module' => 'Class\Something',
                            'function' => 'Class\Something::run',
                            'lineno' => 42,
                        ],
                        [
                            'filename' => '/index.php',
                            'abs_path' => '/var/www/html/index.php',
                            'module' => null,
                            'function' => '{closure}',
                            'lineno' => 126,
                        ],
                    ],
                    'samples' => [
                        [
                            'stack_id' => 0,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.001,
                        ],
                        [
                            'stack_id' => 0,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.002,
                        ],
                        [
                            'stack_id' => 1,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.003,
                        ],
                        [
                            'stack_id' => 2,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.004,
                        ],
                    ],
                    'stacks' => [
                        [
                            0,
                        ],
                        [
                            0,
                            1,
                        ],
                        [
                            0,
                            1,
                            2,
                            3,
                        ],
                    ],
                    'thread_metadata' => (object) [
                        '0' => [
                            'name' => 'main',
                        ],
                    ],
                ],
                'client_sdk' => [
                    'name' => 'sentry.php',
                    'version' => '4.12.0',
                ],
            ],
            new Options([
                'prefixes' => ['/var/www/html'],
            ]),
        ];

        yield 'Function without class' => [
            $event,
            [
                [
                    [
                        'trace' => [
                            [
                                'function' => 'array_map',
                                'file' => '/var/www/html/index.php',
                                'line' => 42,
                            ],
                        ],
                        'timestamp' => 0.001,
                    ],
                ],
            ],
            [
                'profiler_id' => '550e8400e29b41d4a716446655440000',
                'chunk_id' => 'a1b2c3d4e5f64a7b8c9d0e1f2a3b4c5d',
                'platform' => 'php',
                'release' => '1.0.0',
                'environment' => 'dev',
                'version' => '2',
                'profile' => [
                    'frames' => [
                        [
                            'filename' => '/var/www/html/index.php',
                            'abs_path' => '/var/www/html/index.php',
                            'module' => null,
                            'function' => 'array_map',
                            'lineno' => 42,
                        ],
                    ],
                    'samples' => [
                        [
                            'stack_id' => 0,
                            'thread_id' => '0',
                            'timestamp' => 1749368460.001,
                        ],
                    ],
                    'stacks' => [
                        [
                            0,
                        ],
                    ],
                    'thread_metadata' => (object) [
                        '0' => [
                            'name' => 'main',
                        ],
                    ],
                ],
                'client_sdk' => [
                    'name' => 'sentry.php',
                    'version' => '4.12.0',
                ],
            ],
        ];
    }
}

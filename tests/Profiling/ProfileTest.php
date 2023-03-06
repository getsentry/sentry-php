<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Context\OsContext;
use Sentry\Context\RuntimeContext;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Profiling\Profile;

final class ProfileTest extends TestCase
{
    /**
     * @dataProvider formatedDataDataProvider
     */
    public function testGetFormatedData(Event $event, int $startTime, int $stopTime, array $excimerLog, $expectedData): void
    {
        $profile = new Profile();
        $profile->setInitTime((int) (0.001 / 1e+9));

        $profile->setStartTime($startTime);
        $profile->setStopTime($stopTime);

        // 2022-02-28T09:41:00Z
        $profile->setStartTimeStamp(1677573660.0000);

        $profile->setExcimerLog($excimerLog);
        $profile->setEventId((new EventId('815e57b4bb134056ab1840919834689d')));

        $this->assertSame($expectedData, $profile->getFormatedData($event));
    }

    public function formatedDataDataProvider(): \Generator
    {
        $event = Event::createTransaction(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setRelease('1.0.0');
        $event->setEnvironment('dev');
        $event->setTransaction('GET /');
        $event->setContext('trace', [
            'trace_id' => '566e3688a61d4bc888951642d6f14a19',
            'span_id' => '566e3688a61d4bc8',
        ]);
        $event->setRuntimeContext(new RuntimeContext(
            'php',
            '8.2.3'
        ));
        $event->setOsContext(new OsContext(
            'macOS',
            '13.2.1',
            '22D68',
            'Darwin Kernel Version 22.2.0',
            'aarch64'
        ));

        yield [
            $event,
            2000000,
            3000000,
            [
                [
                    'trace' => [
                        [
                            'file' => 'index.php',
                            'line' => 42,
                        ],
                    ],
                    'timestamp' => 0.002,
                ],
                [
                    'trace' => [
                        [
                            'file' => 'index.php',
                            'line' => 42,
                        ],
                        [
                            'class' => 'Function',
                            'function' => 'doStuff',
                            'file' => 'function.php',
                            'line' => 84,
                        ],
                    ],
                    'timestamp' => 0.003,
                ],
            ],
            [
                'device' => [
                    'architecture' => 'aarch64',
                ],
                'event_id' => '815e57b4bb134056ab1840919834689d',
                'os' => [
                    'name' => 'macOS',
                    'version' => '13.2.1',
                    'build_number' => '22D68',
                ],
                'platform' => 'php',
                'release' => '1.0.0',
                'environment' => 'dev',
                'runtime' => [
                    'name' => 'php',
                    'version' => '8.2.3',
                ],
                'timestamp' => '2023-02-28T08:41:00.000+00:00',
                'transaction' => [
                    'id' => 'fc9442f5aef34234bb22b9a615e30ccd',
                    'name' => 'GET /',
                    'trace_id' => '566e3688a61d4bc888951642d6f14a19',
                    'active_thread_id' => '0',
                ],
                'version' => '1',
                'profile' => [
                    'frames' => [
                        [
                            'function' => 'index.php',
                            'filename' => 'index.php',
                            'lineno' => 42,
                        ],
                        [
                            'function' => 'index.php',
                            'filename' => 'index.php',
                            'lineno' => 42,
                        ],
                        [
                            'function' => 'Function::doStuff',
                            'filename' => 'function.php',
                            'lineno' => 84,
                        ],
                    ],
                    'samples' => [
                        [
                            'elapsed_since_start_ns' => 0,
                            'stack_id' => 0,
                            'thread_id' => '0',
                        ],
                        [
                            'elapsed_since_start_ns' => 1000000,
                            'stack_id' => 1,
                            'thread_id' => '0',
                        ],
                    ],
                    'stacks' => [
                        [
                            0,
                        ],
                        [
                            1,
                            2,
                        ],
                    ],
                ],
            ],
        ];

        yield 'Too little samples' => [
            $event,
            2000000,
            3000000,
            [
                [
                    'trace' => [
                        [
                            'file' => 'index.php',
                            'line' => 42,
                        ],
                    ],
                    'timestamp' => 0.002,
                ],
            ],
            null,
        ];

        yield 'Too long duration' => [
            $event,
            2000000,
            32000000000,
            [
                [
                    'trace' => [
                        [
                            'file' => 'index.php',
                            'line' => 42,
                        ],
                    ],
                    'timestamp' => 15,
                ],
                [
                    'trace' => [
                        [
                            'file' => 'index.php',
                            'line' => 42,
                        ],
                        [
                            'class' => 'Function',
                            'function' => 'doStuff',
                            'file' => 'function.php',
                            'line' => 84,
                        ],
                    ],
                    'timestamp' => 15,
                ],
            ],
            null,
        ];
    }
}

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
    public function testGetFormatedData(Event $event, array $rawProfile, $expectedData): void
    {
        $profile = new Profile();
        $profile->setData($rawProfile);
        $profile->setStartTime('2022-02-28T09:41:00Z');
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
            [
                'shared' => [
                    'frames' => [
                        [
                            'name' => 'index.php',
                            'file' => 'index.php',
                        ],
                        [
                            'name' => 'Function::doStuff',
                            'file' => 'function.php',
                        ],
                    ],
                ],
                'profiles' => [
                    [
                        'startValue' => 0,
                        'endValue' => 200000,
                        'samples' => [
                            [
                                1,
                            ],
                            [
                                1,
                                2,
                            ],
                        ],
                        'weights' => [
                            100000,
                            100000,
                        ],
                    ],
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
                'timestamp' => '2022-02-28T09:41:00Z',
                'transaction' => [
                    'id' => 'fc9442f5aef34234bb22b9a615e30ccd',
                    'name' => 'GET /',
                    'trace_id' => '566e3688a61d4bc888951642d6f14a19',
                    'active_thread_id' => '0',
                ],
                'version' => '1',
                'profile' => [
                    'samples' => [
                        'frames' => [
                            [
                                'function' => 'index.php',
                                'filename' => 'index.php',
                            ],
                            [
                                'function' => 'Function::doStuff',
                                'filename' => 'function.php',
                            ],
                        ],
                        [
                            'elapsed_since_start_ns' => 100000,
                            'stack_id' => 0,
                            'thread_id' => '0',
                        ],
                        [
                            'elapsed_since_start_ns' => 200000,
                            'stack_id' => 1,
                            'thread_id' => '0',
                        ],
                    ],
                    'stacks' => [
                        [
                            1,
                        ],
                        [
                            2,
                            1,
                        ],
                    ],
                ],
            ],
        ];

        yield 'Too little samples' => [
            $event,
            [
                'shared' => [
                    'frames' => [
                        [
                            'name' => 'index.php',
                            'file' => 'index.php',
                        ],
                        [
                            'name' => 'Function::doStuff',
                            'file' => 'function.php',
                        ],
                    ],
                ],
                'profiles' => [
                    [
                        'startValue' => 0,
                        'endValue' => 200000,
                        'samples' => [
                            [
                                1,
                            ],
                        ],
                        'weights' => [
                            100000,
                        ],
                    ],
                ],
            ],
            null,
        ];

        yield 'Too long duration' => [
            $event,
            [
                'shared' => [
                    'frames' => [
                        [
                            'name' => 'index.php',
                            'file' => 'index.php',
                        ],
                        [
                            'name' => 'Function::doStuff',
                            'file' => 'function.php',
                        ],
                    ],
                ],
                'profiles' => [
                    [
                        'startValue' => 0,
                        'endValue' => (30 * 1e+9),
                        'samples' => [
                            [
                                1,
                            ],
                            [
                                1,
                                2,
                            ],
                        ],
                        'weights' => [
                            (15 * 1e+9),
                            (15 * 1e+9),
                        ],
                    ],
                ],
            ],
            null,
        ];

        yield 'Empty Excimer profile' => [
            $event,
            [
                'shared' => [
                    'frames' => [],
                ],
                'profiles' => [
                    [
                        'startValue' => 0,
                        'endValue' => 0,
                        'samples' => [],
                        'weights' => [],
                    ],
                ],
            ],
            null,
        ];
    }
}

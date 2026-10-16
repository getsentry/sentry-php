<?php

declare(strict_types=1);

namespace Sentry\Tests\Profiling;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Options;
use Sentry\Profiling\ExcimerLogConverter;
use Sentry\Profiling\ProfileChunk;
use Sentry\Profiling\ProfileData;
use Sentry\Profiling\ProfileFrame;
use Sentry\Profiling\ProfileSample;
use Sentry\Util\JSON;

final class ProfileChunkTest extends TestCase
{
    private const PROFILER_ID = '71bba98d90b545c39f2ae73f702d7ef4';

    // 2023-02-28T08:41:00Z
    private const START_TIMESTAMP = 1677573660.0;

    public function testGetFormattedData(): void
    {
        $event = Event::createEvent();
        $event->setRelease('1.0.0');
        $event->setEnvironment('dev');
        $event->setSdkIdentifier('sentry.php');
        $event->setSdkVersion('4.34.0');

        $chunk = new ProfileChunk(self::PROFILER_ID, self::START_TIMESTAMP, self::getProfileData(new Options([
            'prefixes' => ['/var/www/html'],
        ])));

        $this->assertSame(self::PROFILER_ID, $chunk->getProfilerId());
        $this->assertSame(1, preg_match('/^[0-9a-f]{32}$/', $chunk->getChunkId()));
        $this->assertNotSame(self::PROFILER_ID, $chunk->getChunkId());

        $this->assertEquals([
            'version' => '2',
            'profiler_id' => self::PROFILER_ID,
            'chunk_id' => $chunk->getChunkId(),
            'platform' => 'php',
            'environment' => 'dev',
            'client_sdk' => [
                'name' => 'sentry.php',
                'version' => '4.34.0',
            ],
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
                        'filename' => '/src/Function.php',
                        'abs_path' => '/var/www/html/src/Function.php',
                        'module' => 'Function',
                        'function' => 'Function::doStuff',
                        'lineno' => 7,
                    ],
                ],
                'samples' => [
                    [
                        'stack_id' => 0,
                        'thread_id' => '0',
                        'timestamp' => 1677573660.01,
                    ],
                    [
                        'stack_id' => 1,
                        'thread_id' => '0',
                        'timestamp' => 1677573660.02,
                    ],
                    [
                        'stack_id' => 1,
                        'thread_id' => '0',
                        'timestamp' => 1677573660.03,
                    ],
                ],
                'stacks' => [
                    [0],
                    [1, 0],
                ],
                'thread_metadata' => (object) [
                    '0' => [
                        'name' => 'main',
                    ],
                ],
            ],
            'release' => '1.0.0',
        ], $chunk->getFormattedData($event));
    }

    public function testGetFormattedDataEncodesThreadMetadataAsObject(): void
    {
        $chunk = new ProfileChunk(self::PROFILER_ID, self::START_TIMESTAMP, self::getProfileData());

        $payload = $chunk->getFormattedData(Event::createEvent());

        $this->assertNotNull($payload);
        $this->assertStringContainsString('"thread_metadata":{"0":{"name":"main"}}', JSON::encode($payload));
    }

    public function testGetFormattedDataOmitsMissingRelease(): void
    {
        $chunk = new ProfileChunk(self::PROFILER_ID, self::START_TIMESTAMP, self::getProfileData());

        $payload = $chunk->getFormattedData(Event::createEvent());

        $this->assertNotNull($payload);
        $this->assertArrayNotHasKey('release', $payload);
        $this->assertSame(Event::DEFAULT_ENVIRONMENT, $payload['environment']);
    }

    public function testGetFormattedDataReturnsNullIfThereAreTooFewSamples(): void
    {
        $chunk = new ProfileChunk(self::PROFILER_ID, self::START_TIMESTAMP, new ProfileData(
            [new ProfileFrame('/var/www/html/index.php', '/var/www/html/index.php', '/var/www/html/index.php', null, 42)],
            [[0]],
            [new ProfileSample(0, 0.01)]
        ));

        $this->assertNull($chunk->getFormattedData(Event::createEvent()));
    }

    public function testGetFormattedDataReturnsNullIfThereAreNoSamples(): void
    {
        $chunk = new ProfileChunk(self::PROFILER_ID, self::START_TIMESTAMP, new ProfileData([], [], []));

        $this->assertNull($chunk->getFormattedData(Event::createEvent()));
    }

    public function testChunkIdIsUniquePerChunk(): void
    {
        $firstChunk = new ProfileChunk(self::PROFILER_ID, self::START_TIMESTAMP, new ProfileData([], [], []));
        $secondChunk = new ProfileChunk(self::PROFILER_ID, self::START_TIMESTAMP, new ProfileData([], [], []));

        $this->assertNotSame($firstChunk->getChunkId(), $secondChunk->getChunkId());
    }

    private static function getProfileData(?Options $options = null): ProfileData
    {
        return ExcimerLogConverter::convert(self::getExcimerLog(), $options);
    }

    private static function getExcimerLog(): array
    {
        return [
            [
                'trace' => [
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                ],
                'timestamp' => 0.01,
            ],
            [
                'trace' => [
                    [
                        'class' => 'Function',
                        'function' => 'doStuff',
                        'file' => '/var/www/html/src/Function.php',
                        'line' => 7,
                    ],
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                ],
                'timestamp' => 0.02,
            ],
            [
                'trace' => [
                    [
                        'class' => 'Function',
                        'function' => 'doStuff',
                        'file' => '/var/www/html/src/Function.php',
                        'line' => 7,
                    ],
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                ],
                'timestamp' => 0.03,
            ],
        ];
    }
}

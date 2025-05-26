<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Frame;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Stacktrace;
use Sentry\State\Scope;

use function Sentry\withScope;

final class FrameContextifierIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(string $fixtureFilePath, int $lineNumber, int $contextLines, int $preContextCount, int $postContextCount): void
    {
        $options = new Options(['context_lines' => $contextLines]);
        $integration = new FrameContextifierIntegration();
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(FrameContextifierIntegration::class)
            ->willReturn($integration);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn($options);

        SentrySdk::getCurrentHub()->bindClient($client);

        $stacktrace = new Stacktrace([
            new Frame('[unknown]', $fixtureFilePath, $lineNumber, null, $fixtureFilePath),
        ]);

        $event = Event::createEvent();
        $event->setStacktrace($stacktrace);

        withScope(static function (Scope $scope) use (&$event): void {
            $event = $scope->applyToEvent($event);
        });

        $this->assertNotNull($event);
        $this->assertNotNull($event->getStacktrace());

        $frames = $event->getStacktrace()->getFrames();

        $this->assertCount(1, $frames);
        $this->assertCount($preContextCount, $frames[0]->getPreContext());
        $this->assertCount($postContextCount, $frames[0]->getPostContext());

        $fileContent = explode("\n", $this->getFixtureFileContent($fixtureFilePath));

        for ($i = 0; $i < $preContextCount; ++$i) {
            $this->assertSame(rtrim($fileContent[$i + ($lineNumber - $preContextCount - 1)]), $frames[0]->getPreContext()[$i]);
        }

        $this->assertSame(rtrim($fileContent[$lineNumber - 1]), $frames[0]->getContextLine());

        for ($i = 0; $i < $postContextCount; ++$i) {
            $this->assertSame(rtrim($fileContent[$i + $lineNumber]), $frames[0]->getPostContext()[$i]);
        }
    }

    public static function invokeDataProvider(): \Generator
    {
        yield 'short file' => [
            realpath(__DIR__ . '/../Fixtures/code/ShortFile.php'),
            3,
            2,
            2,
            2,
        ];

        yield 'long file with specified context' => [
            realpath(__DIR__ . '/../Fixtures/code/LongFile.php'),
            8,
            2,
            2,
            2,
        ];

        yield 'long file near end of file' => [
            realpath(__DIR__ . '/../Fixtures/code/LongFile.php'),
            11,
            5,
            5,
            2,
        ];

        yield 'long file near beginning of file' => [
            realpath(__DIR__ . '/../Fixtures/code/LongFile.php'),
            3,
            5,
            2,
            5,
        ];
    }

    public function testInvokeLogsWarningMessageIfSourceCodeExcerptCannotBeRetrievedForFrame(): void
    {
        /** @var MockObject&LoggerInterface $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Failed to get the source code excerpt for the file "file.ext".');

        $options = new Options();
        $integration = new FrameContextifierIntegration($logger);
        $integration->setupOnce();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(FrameContextifierIntegration::class)
            ->willReturn($integration);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn($options);

        SentrySdk::getCurrentHub()->bindClient($client);

        $stacktrace = new Stacktrace([
            new Frame(null, '[internal]', 0),
            new Frame(null, 'file.ext', 10, null, 'file.ext'),
        ]);

        $event = Event::createEvent();
        $event->setStacktrace($stacktrace);

        withScope(static function (Scope $scope) use (&$event): void {
            $event = $scope->applyToEvent($event);
        });

        $this->assertNotNull($event);

        foreach ($stacktrace->getFrames() as $frame) {
            $this->assertNull($frame->getContextLine());
            $this->assertEmpty($frame->getPreContext());
            $this->assertEmpty($frame->getPostContext());
        }
    }

    private function getFixtureFileContent(string $file): string
    {
        $fileContent = file_get_contents($file);

        if ($fileContent === false) {
            throw new \RuntimeException(\sprintf('The fixture file at path "%s" could not be read.', $file));
        }

        return $fileContent;
    }
}

<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests;

use Http\Mock\Client as MockClient;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\ExceptionIntegration;
use Sentry\Options;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tests\Fixtures\classes\CarelessException;
use Sentry\Transport\TransportInterface;

class ClientTest extends TestCase
{
    public function testConstructorInitializesTransactionStack()
    {
        $_SERVER['PATH_INFO'] = '/foo';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([new Options(), $transport])
            ->setMethodsExcept(['captureMessage', 'prepareEvent', 'captureEvent', 'getOptions'])
            ->getMock();

        $client->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($event) {
                /* @var Event $event*/
                $this->assertInstanceOf(Event::class, $event);
                $this->assertEquals('/foo', $event->getTransaction());

                return true;
            }));

        $client->captureMessage('test');

        unset($_SERVER['PATH_INFO']);
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testConstructorInitializesTransactionStackInCli()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([new Options(), $transport])
            ->setMethodsExcept(['captureMessage', 'prepareEvent', 'captureEvent', 'getOptions'])
            ->getMock();

        $client->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($event) {
                /* @var Event $event*/
                $this->assertInstanceOf(Event::class, $event);
                $this->assertNull($event->getTransaction());

                return true;
            }));

        $client->captureMessage('test');
    }

    public function testCaptureMessage()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureMessage'])
            ->getMock();

        $client->expects($this->once())
            ->method('captureEvent')
            ->with([
                'message' => 'foo',
                'level' => Severity::fatal(),
            ]);

        $client->captureMessage('foo', Severity::fatal());
    }

    public function testCaptureException()
    {
        $exception = new \Exception();

        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureException'])
            ->getMock();

        $client->expects($this->once())
            ->method('captureEvent')
            ->with([
                'exception' => $exception,
            ]);

        $client->captureException(new \Exception());
    }

    public function testCapture()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
        ->willReturn('id');

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
            ->setTransport($transport)
            ->getClient();

        $inputData = [
            'transaction' => 'foo bar',
            'level' => Severity::debug(),
            'logger' => 'foo',
            'tags_context' => ['foo', 'bar'],
            'extra_context' => ['foo' => 'bar'],
            'user_context' => ['bar' => 'foo'],
        ];

        $eventId = $client->captureEvent($inputData);

        $this->assertNotNull($eventId);
    }

    public function testAppPathLinux()
    {
        $client = ClientBuilder::create(['project_root' => '/foo/bar'])->getClient();

        $this->assertEquals('/foo/bar/', $client->getOptions()->getProjectRoot());

        $client->getOptions()->setProjectRoot('/foo/baz/');

        $this->assertEquals('/foo/baz/', $client->getOptions()->getProjectRoot());
    }

    public function testAppPathWindows()
    {
        $client = ClientBuilder::create(['project_root' => 'C:\\foo\\bar\\'])->getClient();

        $this->assertEquals('C:\\foo\\bar\\', $client->getOptions()->getProjectRoot());
    }

    private function assertMixedValueAndArray($expected_value, $actual_value)
    {
        if (null === $expected_value) {
            $this->assertNull($actual_value);
        } elseif (true === $expected_value) {
            $this->assertTrue($actual_value);
        } elseif (false === $expected_value) {
            $this->assertFalse($actual_value);
        } elseif (\is_string($expected_value) || \is_numeric($expected_value)) {
            $this->assertEquals($expected_value, $actual_value);
        } elseif (\is_array($expected_value)) {
            $this->assertInternalType('array', $actual_value);
            $this->assertEquals(\count($expected_value), \count($actual_value));
            foreach ($expected_value as $key => $value) {
                $this->assertArrayHasKey($key, $actual_value);
                $this->assertMixedValueAndArray($value, $actual_value[$key]);
            }
        } elseif (\is_callable($expected_value)) {
            $this->assertEquals($expected_value, $actual_value);
        } elseif (\is_object($expected_value)) {
            $this->assertEquals(spl_object_hash($expected_value), spl_object_hash($actual_value));
        }
    }

    public function testSendChecksBeforeSendOption()
    {
        $beforeSendCalled = false;

        $client = ClientBuilder::create([
            'dsn' => 'http://public:secret@example.com/1',
            'before_send' => function () use (&$beforeSendCalled) {
                $beforeSendCalled = true;

                return null;
            },
        ])->getClient();

        $client->captureEvent([]);

        $this->assertTrue($beforeSendCalled);
    }

    /**
     * @dataProvider sampleRateAbsoluteDataProvider
     */
    public function testSampleRateAbsolute($options)
    {
        $httpClient = new MockClient();

        $client = ClientBuilder::create($options)
            ->setHttpClient($httpClient)
            ->getClient();

        for ($i = 0; $i < 10; ++$i) {
            $client->captureMessage('foobar');
        }

        switch ($options['sample_rate']) {
            case 0:
                $this->assertEmpty($httpClient->getRequests());
                break;
            case 1:
                $this->assertNotEmpty($httpClient->getRequests());
                break;
        }
    }

    public function sampleRateAbsoluteDataProvider()
    {
        return [
            [
                [
                    'dsn' => 'http://public:secret@example.com/1',
                    'sample_rate' => 0,
                ],
            ],
            [
                [
                    'dsn' => 'http://public:secret@example.com/1',
                    'sample_rate' => 1,
                ],
            ],
        ];
    }

    /**
     * @dataProvider addBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsTooLowDataProvider
     */
    public function testAddBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsTooLow(int $maxBreadcrumbs): void
    {
        $client = ClientBuilder::create(['max_breadcrumbs' => $maxBreadcrumbs])->getClient();
        $scope = new Scope();

        $client->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'), $scope);

        $this->assertEmpty($scope->getBreadcrumbs());
    }

    public function addBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsTooLowDataProvider(): array
    {
        return [
            [0],
            [-1],
        ];
    }

    public function testAddBreadcrumbRespectsMaxBreadcrumbsLimit(): void
    {
        $client = ClientBuilder::create(['max_breadcrumbs' => 2])->getClient();
        $scope = new Scope();

        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'foo');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'bar');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'baz');

        $client->addBreadcrumb($breadcrumb1, $scope);
        $client->addBreadcrumb($breadcrumb2, $scope);

        $this->assertSame([$breadcrumb1, $breadcrumb2], $scope->getBreadcrumbs());

        $client->addBreadcrumb($breadcrumb3, $scope);

        $this->assertSame([$breadcrumb2, $breadcrumb3], $scope->getBreadcrumbs());
    }

    public function testAddBreadcrumbDoesNothingWhenBeforeBreadcrumbCallbackReturnsNull(): void
    {
        $scope = new Scope();
        $client = ClientBuilder::create(
            [
                'before_breadcrumb' => function (): ?Breadcrumb {
                    return null;
                },
            ]
        )->getClient();

        $client->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'), $scope);

        $this->assertEmpty($scope->getBreadcrumbs());
    }

    public function testAddBreadcrumbStoresBreadcrumbReturnedByBeforeBreadcrumbCallback(): void
    {
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $scope = new Scope();
        $client = ClientBuilder::create(
            [
                'before_breadcrumb' => function () use ($breadcrumb2): ?Breadcrumb {
                    return $breadcrumb2;
                },
            ]
        )->getClient();

        $client->addBreadcrumb($breadcrumb1, $scope);

        $this->assertSame([$breadcrumb2], $scope->getBreadcrumbs());
    }

    public function testHandlingExceptionThrowingAnException()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([new Options(), $transport, [new ExceptionIntegration(new Options())]])
            ->setMethodsExcept(['captureException', 'prepareEvent', 'captureEvent', 'getIntegration', 'getOptions'])
            ->getMock();

        Hub::getCurrent()->bindClient($client);

        $client->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($event) {
                /* @var Event $event*/
                // Make sure the exception is of the careless exception and not the exception thrown inside
                // the __set method of that exception caused by setting the event_id on the exception instance
                $this->assertSame(CarelessException::class, $event->getException()['values'][0]['type']);

                return true;
            }));

        $client->captureException($this->createCarelessExceptionWithStacktrace(), Hub::getCurrent()->getScope());
    }

    private function createCarelessExceptionWithStacktrace()
    {
        try {
            throw new CarelessException('Foo bar');
        } catch (\Exception $ex) {
            return $ex;
        }
    }
}

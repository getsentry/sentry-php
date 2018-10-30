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
use Sentry\Integration\IntegrationStack;
use Sentry\ReprSerializer;
use Sentry\Serializer;
use Sentry\Severity;
use Sentry\State\Scope;
use Sentry\Tests\Fixtures\classes\CarelessException;
use Sentry\TransactionStack;
use Sentry\Transport\TransportInterface;

class ClientTest extends TestCase
{
    public function testConstructorInitializesTransactionStack()
    {
        $_SERVER['PATH_INFO'] = '/foo';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $client = ClientBuilder::create()->getClient();
        $transactionStack = $client->getTransactionStack();

        $this->assertNotEmpty($transactionStack);
        $this->assertEquals('/foo', $transactionStack->peek());
    }

    public function testConstructorInitializesTransactionStackInCli()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertEmpty($client->getTransactionStack());
    }

    public function testGetTransactionStack()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertInstanceOf(TransactionStack::class, $client->getTransactionStack());
    }

    public function testAddMiddleware()
    {
        $middleware = function () {};

        /** @var IntegrationStack|\PHPUnit_Framework_MockObject_MockObject $middlewareStack */
        $middlewareStack = $this->createMock(IntegrationStack::class);
        $middlewareStack->expects($this->once())
            ->method('addMiddleware')
            ->with($middleware, -10);

        $client = ClientBuilder::create()->getClient();

        $reflectionProperty = new \ReflectionProperty($client, 'middlewareStack');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($client, $middlewareStack);
        $reflectionProperty->setAccessible(false);

        $client->addMiddleware($middleware, -10);
    }

    public function testRemoveMiddleware()
    {
        $middleware = function () {};

        /** @var IntegrationStack|\PHPUnit_Framework_MockObject_MockObject $middlewareStack */
        $middlewareStack = $this->createMock(IntegrationStack::class);
        $middlewareStack->expects($this->once())
            ->method('removeMiddleware')
            ->with($middleware);

        $client = ClientBuilder::create()->getClient();

        $reflectionProperty = new \ReflectionProperty($client, 'middlewareStack');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($client, $middlewareStack);
        $reflectionProperty->setAccessible(false);

        $client->removeMiddleware($middleware);
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
            ->method('send');

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

    /**
     * @covers \Sentry\Client::translateSeverity
     * @covers \Sentry\Client::registerSeverityMap
     */
    public function testTranslateSeverity()
    {
        $reflection = new \ReflectionProperty(Client::class, 'severityMap');
        $reflection->setAccessible(true);
        $client = ClientBuilder::create()->getClient();

        $predefined = [E_ERROR, E_WARNING, E_PARSE, E_NOTICE, E_CORE_ERROR, E_CORE_WARNING,
                       E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR, E_USER_WARNING,
                       E_USER_NOTICE, E_STRICT, E_RECOVERABLE_ERROR, ];
        $predefined[] = E_DEPRECATED;
        $predefined[] = E_USER_DEPRECATED;
        $predefined_values = ['debug', 'info', 'warning', 'warning', 'error', 'fatal'];

        // step 1
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('error', $client->translateSeverity(123456));
        // step 2
        $client->registerSeverityMap([]);
        $this->assertMixedValueAndArray([], $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123456));
        // step 3
        $client->registerSeverityMap([123456 => 'foo']);
        $this->assertMixedValueAndArray([123456 => 'foo'], $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 4
        $client->registerSeverityMap([E_USER_ERROR => 'bar']);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 5
        $client->registerSeverityMap([E_USER_ERROR => 'bar', 123456 => 'foo']);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
    }

    public function testSendChecksShouldCaptureOption()
    {
        $shouldCaptureCalled = false;

        $client = ClientBuilder::create([
            'dsn' => 'http://public:secret@example.com/1',
            'should_capture' => function () use (&$shouldCaptureCalled) {
                $shouldCaptureCalled = true;

                return false;
            },
        ])->getClient();

        $client->captureEvent([]);

        $this->assertTrue($shouldCaptureCalled);
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

    public function testSetAllObjectSerialize()
    {
        $client = ClientBuilder::create()->getClient();

        $client->setAllObjectSerialize(true);

        $this->assertTrue($client->getSerializer()->getAllObjectSerialize());
        $this->assertTrue($client->getRepresentationSerializer()->getAllObjectSerialize());

        $client->setAllObjectSerialize(false);

        $this->assertFalse($client->getSerializer()->getAllObjectSerialize());
        $this->assertFalse($client->getRepresentationSerializer()->getAllObjectSerialize());
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

    public function testSetSerializer()
    {
        $client = ClientBuilder::create()->getClient();
        $serializer = $this->createMock(Serializer::class);

        $client->setSerializer($serializer);

        $this->assertSame($serializer, $client->getSerializer());
    }

    public function testSetReprSerializer()
    {
        $client = ClientBuilder::create()->getClient();
        $serializer = $this->createMock(ReprSerializer::class);

        $client->setRepresentationSerializer($serializer);

        $this->assertSame($serializer, $client->getRepresentationSerializer());
    }

//    TODO
//    public function testHandlingExceptionThrowingAnException()
//    {
//        $client = ClientBuilder::create()->getClient();
//        $client->captureException($this->createCarelessExceptionWithStacktrace());
//        $event = $client->getLastEvent();
//        // Make sure the exception is of the careless exception and not the exception thrown inside
//        // the __set method of that exception caused by setting the event_id on the exception instance
//        $this->assertSame(CarelessException::class, $event->getException()['values'][0]['type']);
//    }

//    private function createCarelessExceptionWithStacktrace()
//    {
//        try {
//            throw new CarelessException('Foo bar');
//        } catch (\Exception $ex) {
//            return $ex;
//        }
//    }
}

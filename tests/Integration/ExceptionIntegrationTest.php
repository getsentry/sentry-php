<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Integration\ExceptionIntegration;
use Sentry\Options;
use Sentry\Severity;
use Sentry\Stacktrace;

class ExceptionIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(\Exception $exception, array $clientConfig, array $payload, array $expectedResult)
    {
        $options = new Options($clientConfig);
        $assertHasStacktrace = $options->getAutoLogStacks();

        $event = new Event();
        $integration = new ExceptionIntegration($options);

        ExceptionIntegration::applyToEvent($integration, $event, $exception);

        $this->assertNotNull($event);
        $this->assertArraySubset($expectedResult, $event->toArray());
        $this->assertArrayNotHasKey('values', $event->getException());
        $this->assertArrayHasKey('values', $event->toArray()['exception']);

        foreach ($event->getException() as $exceptionData) {
            if ($assertHasStacktrace) {
                $this->assertArrayHasKey('stacktrace', $exceptionData);
                $this->assertInstanceOf(Stacktrace::class, $exceptionData['stacktrace']);
            } else {
                $this->assertArrayNotHasKey('stacktrace', $exceptionData);
            }
        }
    }

    public function invokeDataProvider()
    {
        return [
            [
                new \RuntimeException('foo'),
                [],
                [],
                [
                    'level' => Severity::ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \RuntimeException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \RuntimeException('foo'),
                [
                    'auto_log_stacks' => false,
                ],
                [],
                [
                    'level' => Severity::ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \RuntimeException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \ErrorException('foo', 0, E_USER_WARNING),
                [],
                [],
                [
                    'level' => Severity::WARNING,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \ErrorException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \BadMethodCallException('baz', 0, new \BadFunctionCallException('bar', 0, new \LogicException('foo', 0))),
                [
                    'excluded_exceptions' => [\BadMethodCallException::class],
                ],
                [],
                [
                    'level' => Severity::ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \LogicException::class,
                                'value' => 'foo',
                            ],
                            [
                                'type' => \BadFunctionCallException::class,
                                'value' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testInvokeWithExceptionContainingLatin1Characters()
    {
        $options = new Options(['mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8']]);

        $event = new Event();
        $utf8String = 'äöü';
        $latin1String = utf8_decode($utf8String);

        $integration = new ExceptionIntegration($options);

        ExceptionIntegration::applyToEvent($integration, $event, new \Exception($latin1String));

        $this->assertNotNull($event);

        $expectedValue = [
                [
                    'type' => \Exception::class,
                    'value' => $utf8String,
                ],
        ];

        $this->assertArraySubset($expectedValue, $event->getException());
    }

    public function testInvokeWithExceptionContainingInvalidUtf8Characters()
    {
        $options = new Options();
        $event = new Event();

        $integration = new ExceptionIntegration($options);

        $malformedString = "\xC2\xA2\xC2"; // ill-formed 2-byte character U+00A2 (CENT SIGN)
        ExceptionIntegration::applyToEvent($integration, $event, new \Exception($malformedString));

        $this->assertNotNull($event);

        $expectedValue = [
                [
                    'type' => \Exception::class,
                    'value' => "\xC2\xA2\x3F",
                ],
        ];

        $this->assertArraySubset($expectedValue, $event->getException());
    }

    public function testInvokeWithExceptionThrownInLatin1File()
    {
        $options = new Options([
            'auto_log_stacks' => true,
            'mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8'],
        ]);

        $event = new Event();

        $integration = new ExceptionIntegration($options);

        ExceptionIntegration::applyToEvent($integration, $event, require_once __DIR__ . '/../Fixtures/code/Latin1File.php');

        $this->assertNotNull($event);

        $result = $event->getException();
        $expectedValue = [
                [
                    'type' => \Exception::class,
                    'value' => 'foo',
                ],
        ];

        $this->assertArraySubset($expectedValue, $result);

        $latin1StringFound = false;

        /** @var \Sentry\Frame $frame */
        foreach ($result[0]['stacktrace']->getFrames() as $frame) {
            if (null !== $frame->getPreContext() && \in_array('// äöü', $frame->getPreContext(), true)) {
                $latin1StringFound = true;

                break;
            }
        }

        $this->assertTrue($latin1StringFound);
    }

    public function testInvokeWithAutoLogStacksDisabled()
    {
        $options = new Options(['auto_log_stacks' => false]);
        $event = new Event();

        $integration = new ExceptionIntegration($options);

        ExceptionIntegration::applyToEvent($integration, $event, new \Exception('foo'));

        $this->assertNotNull($event);

        $result = $event->getException();

        $this->assertNotEmpty($result);
        $this->assertInternalType('array', $result[0]);
        $this->assertEquals(\Exception::class, $result[0]['type']);
        $this->assertEquals('foo', $result[0]['value']);
        $this->assertArrayNotHasKey('stacktrace', $result[0]);
    }
}

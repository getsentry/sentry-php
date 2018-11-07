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
use Sentry\Integration\ExceptionIntegrationInterface;
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
        $integration = new ExceptionIntegrationInterface($options);

        $returnedEvent = ExceptionIntegrationInterface::applyToEvent($integration, $event, $exception);

        $this->assertNotNull($returnedEvent);
        $this->assertArraySubset($expectedResult, $returnedEvent->toArray());

        foreach ($returnedEvent->getException()['values'] as $exceptionData) {
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

        $integration = new ExceptionIntegrationInterface($options);

        $returnedEvent = ExceptionIntegrationInterface::applyToEvent($integration, $event, new \Exception($latin1String));
        $this->assertNotNull($returnedEvent);

        $expectedValue = [
            'values' => [
                [
                    'type' => \Exception::class,
                    'value' => $utf8String,
                ],
            ],
        ];

        $this->assertArraySubset($expectedValue, $returnedEvent->getException());
    }

    public function testInvokeWithExceptionContainingInvalidUtf8Characters()
    {
        $options = new Options();
        $event = new Event();

        $integration = new ExceptionIntegrationInterface($options);

        $malformedString = "\xC2\xA2\xC2"; // ill-formed 2-byte character U+00A2 (CENT SIGN)
        $returnedEvent = ExceptionIntegrationInterface::applyToEvent($integration, $event, new \Exception($malformedString));
        $this->assertNotNull($returnedEvent);

        $expectedValue = [
            'values' => [
                [
                    'type' => \Exception::class,
                    'value' => "\xC2\xA2\x3F",
                ],
            ],
        ];

        $this->assertArraySubset($expectedValue, $returnedEvent->getException());
    }

    public function testInvokeWithExceptionThrownInLatin1File()
    {
        $options = new Options([
            'auto_log_stacks' => true,
            'mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8'],
        ]);

        $event = new Event();

        $integration = new ExceptionIntegrationInterface($options);

        $returnedEvent = ExceptionIntegrationInterface::applyToEvent($integration, $event, require_once __DIR__ . '/../Fixtures/code/Latin1File.php');
        $this->assertNotNull($returnedEvent);

        $result = $returnedEvent->getException();
        $expectedValue = [
            'values' => [
                [
                    'type' => \Exception::class,
                    'value' => 'foo',
                ],
            ],
        ];

        $this->assertArraySubset($expectedValue, $result);

        $latin1StringFound = false;

        /** @var \Sentry\Frame $frame */
        foreach ($result['values'][0]['stacktrace']->getFrames() as $frame) {
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

        $integration = new ExceptionIntegrationInterface($options);

        $returnedEvent = ExceptionIntegrationInterface::applyToEvent($integration, $event, new \Exception('foo'));
        $this->assertNotNull($returnedEvent);

        $result = $returnedEvent->getException();
        $this->assertNotEmpty($result);
        $this->assertInternalType('array', $result['values'][0]);
        $this->assertEquals(\Exception::class, $result['values'][0]['type']);
        $this->assertEquals('foo', $result['values'][0]['value']);
        $this->assertArrayNotHasKey('stacktrace', $result['values'][0]);
    }
}

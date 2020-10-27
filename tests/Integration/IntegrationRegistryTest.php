<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\IntegrationRegistry;
use Sentry\Integration\RequestIntegration;
use Sentry\Integration\TransactionIntegration;
use Sentry\Options;

final class IntegrationRegistryTest extends TestCase
{
    public function testGetInstance(): void
    {
        $instance1 = IntegrationRegistry::getInstance();
        $instance2 = IntegrationRegistry::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * @dataProvider setupIntegrationsDataProvider
     */
    public function testSetupIntegrations(Options $options, array $expectedDebugMessages, array $expectedIntegrations): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(\count($expectedDebugMessages)))
            ->method('debug')
            ->withConsecutive(...array_map(
                static function (string $debugMessage): array {
                    return [
                        $debugMessage,
                        [],
                    ];
                },
                $expectedDebugMessages
            ));

        $this->assertEquals($expectedIntegrations, IntegrationRegistry::getInstance()->setupIntegrations($options, $logger));
    }

    public function setupIntegrationsDataProvider(): iterable
    {
        $integration1 = new class() implements IntegrationInterface {
            public function setupOnce(): void
            {
            }
        };

        $integration2 = new class() implements IntegrationInterface {
            public function setupOnce(): void
            {
            }
        };

        $integration1ClassName = \get_class($integration1);
        $integration2ClassName = \get_class($integration2);

        yield 'No default integrations and no user integrations' => [
            new Options([
                'default_integrations' => false,
            ]),
            [],
            [],
        ];

        yield 'Default integrations and no user integrations' => [
            new Options([
                'default_integrations' => true,
            ]),
            [
                'The "Sentry\\Integration\\ExceptionListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\ErrorListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\FatalErrorListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\RequestIntegration" integration has been installed.',
                'The "Sentry\\Integration\\TransactionIntegration" integration has been installed.',
                'The "Sentry\\Integration\\FrameContextifierIntegration" integration has been installed.',
                'The "Sentry\\Integration\\EnvironmentIntegration" integration has been installed.',
            ],
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => new ErrorListenerIntegration(),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
            ],
        ];

        yield 'No default integrations and some user integrations' => [
            new Options([
                'default_integrations' => false,
                'integrations' => [
                    $integration1,
                    $integration2,
                ],
            ]),
            [
                "The \"$integration1ClassName\" integration has been installed.",
                "The \"$integration2ClassName\" integration has been installed.",
            ],
            [
                $integration1ClassName => $integration1,
                $integration2ClassName => $integration2,
            ],
        ];

        yield 'Default integrations and some user integrations' => [
            new Options([
                'default_integrations' => true,
                'integrations' => [
                    $integration1,
                    $integration2,
                ],
            ]),
            [
                'The "Sentry\\Integration\\ExceptionListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\ErrorListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\FatalErrorListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\RequestIntegration" integration has been installed.',
                'The "Sentry\\Integration\\TransactionIntegration" integration has been installed.',
                'The "Sentry\\Integration\\FrameContextifierIntegration" integration has been installed.',
                'The "Sentry\\Integration\\EnvironmentIntegration" integration has been installed.',
                "The \"$integration1ClassName\" integration has been installed.",
                "The \"$integration2ClassName\" integration has been installed.",
            ],
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => new ErrorListenerIntegration(),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
                $integration1ClassName => $integration1,
                $integration2ClassName => $integration2,
            ],
        ];

        yield 'Default integrations and some user integrations, one of which is also a default integration' => [
            new Options([
                'default_integrations' => true,
                'integrations' => [
                    new TransactionIntegration(),
                    $integration1,
                ],
            ]),
            [
                'The "Sentry\\Integration\\ExceptionListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\ErrorListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\FatalErrorListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\RequestIntegration" integration has been installed.',
                'The "Sentry\\Integration\\FrameContextifierIntegration" integration has been installed.',
                'The "Sentry\\Integration\\EnvironmentIntegration" integration has been installed.',
                'The "Sentry\\Integration\\TransactionIntegration" integration has been installed.',
                "The \"$integration1ClassName\" integration has been installed.",
            ],
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => new ErrorListenerIntegration(),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                $integration1ClassName => $integration1,
            ],
        ];

        yield 'No default integrations and some user integrations are repeated twice' => [
            new Options([
                'default_integrations' => false,
                'integrations' => [
                    $integration1,
                    $integration1,
                ],
            ]),
            [
                "The \"$integration1ClassName\" integration has been installed.",
            ],
            [
                $integration1ClassName => $integration1,
            ],
        ];

        yield 'No default integrations and a callable as user integrations' => [
            new Options([
                'default_integrations' => false,
                'integrations' => static function (array $defaultIntegrations): array {
                    return $defaultIntegrations;
                },
            ]),
            [],
            [],
        ];

        yield 'Default integrations and a callable as user integrations' => [
            new Options([
                'default_integrations' => true,
                'integrations' => static function (array $defaultIntegrations): array {
                    return $defaultIntegrations;
                },
            ]),
            [
                'The "Sentry\\Integration\\ExceptionListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\ErrorListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\FatalErrorListenerIntegration" integration has been installed.',
                'The "Sentry\\Integration\\RequestIntegration" integration has been installed.',
                'The "Sentry\\Integration\\TransactionIntegration" integration has been installed.',
                'The "Sentry\\Integration\\FrameContextifierIntegration" integration has been installed.',
                'The "Sentry\\Integration\\EnvironmentIntegration" integration has been installed.',
            ],
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => new ErrorListenerIntegration(),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
            ],
        ];
    }

    /**
     * @dataProvider setupIntegrationsThrowsExceptionIfValueReturnedFromOptionIsNotValidDataProvider
     */
    public function testSetupIntegrationsThrowsExceptionIfValueReturnedFromOptionIsNotValid($value, string $expectedExceptionMessage): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        IntegrationRegistry::getInstance()->setupIntegrations(
            new Options([
                'default_integrations' => false,
                'integrations' => static function () use ($value) {
                    return $value;
                },
            ]),
            $this->createMock(LoggerInterface::class)
        );
    }

    public function setupIntegrationsThrowsExceptionIfValueReturnedFromOptionIsNotValidDataProvider(): iterable
    {
        yield [
            12.34,
            'Expected the callback set for the "integrations" option to return a list of integrations. Got: "float".',
        ];

        yield [
            new \stdClass(),
            'Expected the callback set for the "integrations" option to return a list of integrations. Got: "stdClass".',
        ];
    }

    public function testSetupIntegrationsIsIdempotent(): void
    {
        $integration = $this->createMock(IntegrationInterface::class);
        $integration->expects($this->once())
            ->method('setupOnce');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug');

        $options = new Options([
            'default_integrations' => false,
            'integrations' => [$integration],
        ]);

        IntegrationRegistry::getInstance()->setupIntegrations($options, $logger);
        IntegrationRegistry::getInstance()->setupIntegrations($options, $logger);
    }
}

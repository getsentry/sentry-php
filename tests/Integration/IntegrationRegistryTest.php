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
use Sentry\Integration\ModulesIntegration;
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
    public function testSetupIntegrations(Options $options, array $expectedIntegrations): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        if (\count($expectedIntegrations) > 0) {
            $logger->expects($this->once())
                ->method('debug')
                ->with(\sprintf('The "%s" integration(s) have been installed.', implode(', ', array_keys($expectedIntegrations))), []);
        } else {
            $logger->expects($this->never())
                ->method('debug');
        }

        $this->assertEquals($expectedIntegrations, IntegrationRegistry::getInstance()->setupIntegrations($options, $logger));
    }

    public static function setupIntegrationsDataProvider(): iterable
    {
        $integration1 = new class implements IntegrationInterface {
            public function setupOnce(): void
            {
            }
        };

        $integration2 = new class implements IntegrationInterface {
            public function setupOnce(): void
            {
            }
        };

        $integration1ClassName = \get_class($integration1);
        $integration2ClassName = \get_class($integration2);

        yield 'No default integrations and no user integrations' => [
            new Options([
                'dsn' => 'http://public@example.com/sentry/1',
                'default_integrations' => false,
            ]),
            [],
        ];

        yield 'Default integrations and no user integrations' => [
            $options = new Options([
                'dsn' => 'http://public@example.com/sentry/1',
                'default_integrations' => true,
            ]),
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => ErrorListenerIntegration::make($options),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
                ModulesIntegration::class => new ModulesIntegration(),
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
                $integration1ClassName => $integration1,
                $integration2ClassName => $integration2,
            ],
        ];

        yield 'Default integrations and some user integrations' => [
            $options = new Options([
                'dsn' => 'http://public@example.com/sentry/1',
                'default_integrations' => true,
                'integrations' => [
                    $integration1,
                    $integration2,
                ],
            ]),
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => ErrorListenerIntegration::make($options),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
                ModulesIntegration::class => new ModulesIntegration(),
                $integration1ClassName => $integration1,
                $integration2ClassName => $integration2,
            ],
        ];

        yield 'Default integrations and some user integrations, one of which is also a default integration' => [
            $options = new Options([
                'dsn' => 'http://public@example.com/sentry/1',
                'default_integrations' => true,
                'integrations' => [
                    new TransactionIntegration(),
                    $integration1,
                ],
            ]),
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => ErrorListenerIntegration::make($options),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
                ModulesIntegration::class => new ModulesIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                $integration1ClassName => $integration1,
            ],
        ];

        yield 'Default integrations and one user integration, the ModulesIntegration is also a default integration' => [
            $options = new Options([
                'dsn' => 'http://public@example.com/sentry/1',
                'default_integrations' => true,
                'integrations' => [
                    new ModulesIntegration(),
                ],
            ]),
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => ErrorListenerIntegration::make($options),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
                ModulesIntegration::class => new ModulesIntegration(),
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
        ];

        yield 'Default integrations and a callable as user integrations' => [
            $options = new Options([
                'dsn' => 'http://public@example.com/sentry/1',
                'default_integrations' => true,
                'integrations' => static function (array $defaultIntegrations): array {
                    return $defaultIntegrations;
                },
            ]),
            [
                ExceptionListenerIntegration::class => new ExceptionListenerIntegration(),
                ErrorListenerIntegration::class => ErrorListenerIntegration::make($options),
                FatalErrorListenerIntegration::class => new FatalErrorListenerIntegration(),
                RequestIntegration::class => new RequestIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
                ModulesIntegration::class => new ModulesIntegration(),
            ],
        ];

        yield 'Default integrations with DSN set to null' => [
            new Options([
                'dsn' => null,
                'default_integrations' => true,
                'integrations' => static function (array $defaultIntegrations): array {
                    return $defaultIntegrations;
                },
            ]),
            [
                RequestIntegration::class => new RequestIntegration(),
                TransactionIntegration::class => new TransactionIntegration(),
                FrameContextifierIntegration::class => new FrameContextifierIntegration(),
                EnvironmentIntegration::class => new EnvironmentIntegration(),
                ModulesIntegration::class => new ModulesIntegration(),
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

    public static function setupIntegrationsThrowsExceptionIfValueReturnedFromOptionIsNotValidDataProvider(): iterable
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

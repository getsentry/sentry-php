<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\KeyValueCollectionBehavior;

final class KeyValueCollectionBehaviorTest extends TestCase
{
    public function testOff(): void
    {
        $behavior = KeyValueCollectionBehavior::off();

        $this->assertSame('off', $behavior->getMode());
        $this->assertSame([], $behavior->getTerms());
        $this->assertTrue($behavior->isOff());
    }

    public function testDenyList(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList(['x-custom']);

        $this->assertSame('denyList', $behavior->getMode());
        $this->assertSame(['x-custom'], $behavior->getTerms());
        $this->assertFalse($behavior->isOff());
        $this->assertSame([], KeyValueCollectionBehavior::denyList()->getTerms());
    }

    public function testAllowList(): void
    {
        $behavior = KeyValueCollectionBehavior::allowList(['x-request-id']);

        $this->assertSame('allowList', $behavior->getMode());
        $this->assertSame(['x-request-id'], $behavior->getTerms());
        $this->assertFalse($behavior->isOff());
    }

    /**
     * @dataProvider fromArrayDataProvider
     *
     * @param array{mode: string, terms?: string[]} $config
     */
    public function testFromArray(array $config, KeyValueCollectionBehavior $expected): void
    {
        $this->assertEquals($expected, KeyValueCollectionBehavior::fromArray($config));
    }

    public static function fromArrayDataProvider(): \Generator
    {
        yield 'off ignores terms' => [
            ['mode' => 'off', 'terms' => ['theme']],
            KeyValueCollectionBehavior::off(),
        ];

        yield 'deny list' => [
            ['mode' => 'denyList', 'terms' => ['theme']],
            KeyValueCollectionBehavior::denyList(['theme']),
        ];

        yield 'deny list without terms' => [
            ['mode' => 'denyList'],
            KeyValueCollectionBehavior::denyList(),
        ];

        yield 'allow list' => [
            ['mode' => 'allowList', 'terms' => ['theme']],
            KeyValueCollectionBehavior::allowList(['theme']),
        ];
    }

    public function testFromArrayRejectsUnknownModes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid collection mode "allowlist".');

        KeyValueCollectionBehavior::fromArray(['mode' => 'allowlist', 'terms' => ['theme']]);
    }
}

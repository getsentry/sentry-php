<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\UserDataBag;

final class UserDataBagTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $userDataBag = new UserDataBag();
        $userDataBag->setId('unique_id');
        $userDataBag->setIpAddress('127.0.0.1');
        $userDataBag->setEmail('foo@example.com');
        $userDataBag->setUsername('my_user');
        $userDataBag->setSegment('my_segment');
        $userDataBag->setMetadata('subscription', 'basic');

        $this->assertSame('unique_id', $userDataBag->getId());
        $this->assertSame('127.0.0.1', $userDataBag->getIpAddress());
        $this->assertSame('foo@example.com', $userDataBag->getEmail());
        $this->assertSame('my_user', $userDataBag->getUsername());
        $this->assertSame('my_segment', $userDataBag->getSegment());
        $this->assertSame(['subscription' => 'basic'], $userDataBag->getMetadata());
    }

    /**
     * @dataProvider createFromArrayDataProvider
     */
    public function testCreateFromArray(array $data, $expectedId, ?string $expectedIpAddress, ?string $expectedEmail, ?string $expectedUsername, ?string $expectedSegment, array $expectedMetadata): void
    {
        $userDataBag = UserDataBag::createFromArray($data);

        $this->assertSame($expectedId, $userDataBag->getId());
        $this->assertSame($expectedIpAddress, $userDataBag->getIpAddress());
        $this->assertSame($expectedEmail, $userDataBag->getEmail());
        $this->assertSame($expectedUsername, $userDataBag->getUsername());
        $this->assertSame($expectedSegment, $userDataBag->getSegment());
        $this->assertSame($expectedMetadata, $userDataBag->getMetadata());
    }

    public static function createFromArrayDataProvider(): iterable
    {
        yield [
            ['id' => 1234],
            1234,
            null,
            null,
            null,
            null,
            [],
        ];

        yield [
            ['id' => 'unique_id'],
            'unique_id',
            null,
            null,
            null,
            null,
            [],
        ];

        yield [
            ['ip_address' => '127.0.0.1'],
            null,
            '127.0.0.1',
            null,
            null,
            null,
            [],
        ];

        yield [
            ['email' => 'foo@example.com'],
            null,
            null,
            'foo@example.com',
            null,
            null,
            [],
        ];

        yield [
            ['username' => 'my_user'],
            null,
            null,
            null,
            'my_user',
            null,
            [],
        ];

        yield [
            ['segment' => 'my_segment'],
            null,
            null,
            null,
            null,
            'my_segment',
            [],
        ];

        yield [
            ['subscription' => 'basic'],
            null,
            null,
            null,
            null,
            null,
            ['subscription' => 'basic'],
        ];
    }

    /**
     * @dataProvider unexpectedValueForIdFieldDataProvider
     */
    public function testConstructorThrowsIfIdValueIsUnexpectedValue($value, string $expectedExceptionMessage): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new UserDataBag($value);
    }

    /**
     * @dataProvider unexpectedValueForIdFieldDataProvider
     */
    public function testSetIdThrowsIfValueIsUnexpectedValue($value, string $expectedExceptionMessage): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $userDataBag = new UserDataBag();
        $userDataBag->setId($value);
    }

    /**
     * @dataProvider unexpectedValueForIdFieldDataProvider
     */
    public function testCreateFromUserIdentifierThrowsIfArgumentIsInvalid($value, string $expectedExceptionMessage): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        UserDataBag::createFromUserIdentifier($value);
    }

    public static function unexpectedValueForIdFieldDataProvider(): iterable
    {
        yield [
            12.34,
            'Expected an integer or string value for the $id argument. Got: "float".',
        ];

        yield [
            new \stdClass(),
            'Expected an integer or string value for the $id argument. Got: "stdClass".',
        ];
    }

    public function testConstructorThrowsIfIpAddressArgumentIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "foo" value is not a valid IP address.');

        new UserDataBag(null, null, 'foo');
    }

    public function testSetIpAddressThrowsIfArgumentIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "foo" value is not a valid IP address.');

        $userDataBag = new UserDataBag();
        $userDataBag->setIpAddress('foo');
    }

    public function testCreateFromIpAddressThrowsIfArgumentIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "foo" value is not a valid IP address.');

        UserDataBag::createFromUserIpAddress('foo');
    }

    public function testMerge(): void
    {
        $userDataBag = UserDataBag::createFromUserIdentifier('unique_id');
        $userDataBag->setMetadata('subscription', 'basic');

        $userDataBagToMergeWith = UserDataBag::createFromUserIpAddress('127.0.0.1');
        $userDataBagToMergeWith->setEmail('foo@example.com');
        $userDataBagToMergeWith->setUsername('my_user');
        $userDataBagToMergeWith->setSegment('my_segment');
        $userDataBagToMergeWith->setMetadata('subscription', 'lifetime');
        $userDataBagToMergeWith->setMetadata('subscription_expires_at', '2020-08-20');

        $userDataBag = $userDataBag->merge($userDataBagToMergeWith);

        $this->assertNull($userDataBag->getId());
        $this->assertSame('127.0.0.1', $userDataBag->getIpAddress());
        $this->assertSame('foo@example.com', $userDataBag->getEmail());
        $this->assertSame('my_user', $userDataBag->getUsername());
        $this->assertSame('my_segment', $userDataBag->getSegment());
        $this->assertSame(['subscription' => 'lifetime', 'subscription_expires_at' => '2020-08-20'], $userDataBag->getMetadata());
    }
}

<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\UserDataBag;

final class UserDataBagTest extends TestCase
{
    public function testConstructorThrowsIfArgumentIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "foo" value is not a valid IP address.');

        UserDataBag::createFromUserIpAddress('foo');
    }

    public function testGettersAndSetters(): void
    {
        $userDataBag = UserDataBag::createFromUserIdentifier('unique_id');
        $userDataBag->setIpAddress('127.0.0.1');
        $userDataBag->setEmail('foo@example.com');
        $userDataBag->setUsername('my_user');
        $userDataBag['subscription'] = 'basic';

        $this->assertSame('unique_id', $userDataBag->getId());
        $this->assertSame('127.0.0.1', $userDataBag->getIpAddress());
        $this->assertSame('foo@example.com', $userDataBag->getEmail());
        $this->assertSame('my_user', $userDataBag->getUsername());
        $this->assertSame(['subscription' => 'basic'], $userDataBag->getMetadata());
    }

    public function testSetIdThrowsIfBothArgumentAndIpAddressAreNull(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Either the IP address or the ID must be set.');

        $userDataBag = UserDataBag::createFromUserIdentifier('unique_id');
        $userDataBag->setId(null);
    }

    public function testSetIpAddressThrowsIfBothArgumentAndIdAreNull(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Either the IP address or the ID must be set.');

        $userDataBag = UserDataBag::createFromUserIpAddress('127.0.0.1');
        $userDataBag->setIpAddress(null);
    }

    public function testSetIpAddressThrowsIfArgumentIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "foo" value is not a valid IP address.');

        $userDataBag = UserDataBag::createFromUserIpAddress('127.0.0.1');
        $userDataBag->setIpAddress('foo');
    }

    public function testMerge(): void
    {
        $userDataBag = UserDataBag::createFromUserIdentifier('unique_id');
        $userDataBag['subscription'] = 'basic';

        $userDataBagToMergeWith = UserDataBag::createFromUserIpAddress('127.0.0.1');
        $userDataBagToMergeWith->setEmail('foo@example.com');
        $userDataBagToMergeWith->setUsername('my_user');
        $userDataBagToMergeWith['subscription'] = 'lifetime';
        $userDataBagToMergeWith['subscription_expires_at'] = '2020-08-20';

        $userDataBag = $userDataBag->merge($userDataBagToMergeWith);

        $this->assertNull($userDataBag->getId());
        $this->assertSame('127.0.0.1', $userDataBag->getIpAddress());
        $this->assertSame('foo@example.com', $userDataBag->getEmail());
        $this->assertSame('my_user', $userDataBag->getUsername());
        $this->assertSame(['subscription' => 'lifetime', 'subscription_expires_at' => '2020-08-20'], $userDataBag->getMetadata());
    }
}

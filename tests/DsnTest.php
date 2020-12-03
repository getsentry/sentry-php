<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Dsn;

final class DsnTest extends TestCase
{
    /**
     * @dataProvider createFromStringDataProvider
     */
    public function testCreateFromString(
        string $value,
        string $expectedScheme,
        string $expectedHost,
        int $expectedPort,
        string $expectedPublicKey,
        ?string $expectedSecretKey,
        int $expectedProjectId,
        string $expectedPath
    ): void {
        $dsn = Dsn::createFromString($value);

        $this->assertSame($expectedScheme, $dsn->getScheme());
        $this->assertSame($expectedHost, $dsn->getHost());
        $this->assertSame($expectedPort, $dsn->getPort());
        $this->assertSame($expectedPublicKey, $dsn->getPublicKey());
        $this->assertSame($expectedSecretKey, $dsn->getSecretKey());
        $this->assertSame($expectedProjectId, $dsn->getProjectId());
        $this->assertSame($expectedPath, $dsn->getPath());
    }

    public function createFromStringDataProvider(): \Generator
    {
        yield [
            'http://public@example.com/sentry/1',
            'http',
            'example.com',
            80,
            'public',
            null,
            1,
            '/sentry',
        ];

        yield [
            'http://public@example.com/1',
            'http',
            'example.com',
            80,
            'public',
            null,
            1,
            '',
        ];

        yield [
            'http://public:secret@example.com/1',
            'http',
            'example.com',
            80,
            'public',
            'secret',
            1,
            '',
        ];

        yield [
            'http://public@example.com:80/1',
            'http',
            'example.com',
            80,
            'public',
            null,
            1,
            '',
        ];

        yield [
            'http://public@example.com:8080/1',
            'http',
            'example.com',
            8080,
            'public',
            null,
            1,
            '',
        ];

        yield [
            'https://public@example.com/1',
            'https',
            'example.com',
            443,
            'public',
            null,
            1,
            '',
        ];

        yield [
            'https://public@example.com:443/1',
            'https',
            'example.com',
            443,
            'public',
            null,
            1,
            '',
        ];

        yield [
            'https://public@example.com:4343/1',
            'https',
            'example.com',
            4343,
            'public',
            null,
            1,
            '',
        ];
    }

    /**
     * @dataProvider createFromStringThrowsExceptionIfValueIsInvalidDataProvider
     */
    public function testCreateFromStringThrowsExceptionIfValueIsInvalid(string $value, string $expectedExceptionMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        Dsn::createFromString($value);
    }

    public function createFromStringThrowsExceptionIfValueIsInvalidDataProvider(): \Generator
    {
        yield 'invalid DSN' => [
            ':',
            'The ":" DSN is invalid.',
        ];

        yield 'missing scheme' => [
            '://public@example.com/sentry/1',
            'The "://public@example.com/sentry/1" DSN must contain a scheme, a host, a user and a path component.',
        ];

        yield 'missing public key' => [
            'http://:secret@example.com/sentry/1',
            'The "http://:secret@example.com/sentry/1" DSN must contain a scheme, a host, a user and a path component.',
        ];

        yield 'missing secret key' => [
            'http://public:@example.com/sentry/1',
            'The "http://public:@example.com/sentry/1" DSN must contain a valid secret key.',
        ];

        yield 'missing host' => [
            '/sentry/1',
            'The "/sentry/1" DSN must contain a scheme, a host, a user and a path component.',
        ];

        yield 'missing path' => [
            'http://public@example.com',
            'The "http://public@example.com" DSN must contain a scheme, a host, a user and a path component.',
        ];

        yield 'unsupported scheme' => [
            'tcp://public:secret@example.com/1',
            'The scheme of the "tcp://public:secret@example.com/1" DSN must be either "http" or "https".',
        ];

        yield 'invalid project ID (char instead of number)' => [
            'http://public:secret@example.com/j',
            'DSN must contain a valid project ID.',
        ];

        yield 'invalid project ID (negative number)' => [
            'http://public:secret@example.com/-2',
            'DSN must contain a valid project ID.',
        ];
    }

    /**
     * @dataProvider getStoreApiEndpointUrlDataProvider
     */
    public function testGetStoreApiEndpointUrl(string $value, string $expectedUrl): void
    {
        $dsn = Dsn::createFromString($value);

        $this->assertSame($expectedUrl, $dsn->getStoreApiEndpointUrl());
    }

    public function getStoreApiEndpointUrlDataProvider(): \Generator
    {
        yield [
            'http://public@example.com/sentry/1',
            'http://example.com/sentry/api/1/store/',
        ];

        yield [
            'http://public@example.com/1',
            'http://example.com/api/1/store/',
        ];

        yield [
            'http://public@example.com:8080/sentry/1',
            'http://example.com:8080/sentry/api/1/store/',
        ];

        yield [
            'https://public@example.com/sentry/1',
            'https://example.com/sentry/api/1/store/',
        ];

        yield [
            'https://public@example.com:4343/sentry/1',
            'https://example.com:4343/sentry/api/1/store/',
        ];
    }

    /**
     * @dataProvider toStringDataProvider
     */
    public function testToString(string $value): void
    {
        $this->assertSame($value, (string) Dsn::createFromString($value));
    }

    public function toStringDataProvider(): array
    {
        return [
            ['http://public@example.com/sentry/1'],
            ['http://public:secret@example.com/sentry/1'],
            ['http://public@example.com/1'],
            ['http://public@example.com:8080/sentry/1'],
            ['https://public@example.com/sentry/1'],
            ['https://public@example.com:4343/sentry/1'],
        ];
    }
}

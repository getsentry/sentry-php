<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Util\Str;

final class StrTest extends TestCase
{
    /**
     * @dataProvider vsprintfOrNullDataProvider
     */
    public function testVsprintfOrNull(string $message, array $values, ?string $expected): void
    {
        $this->assertSame($expected, Str::vsprintfOrNull($message, $values));
    }

    /**
     * Demo for the mutation testing job, remove before merging: asserts nothing a bug would change.
     */
    public function testVsprintfOrNullReturnsAString(): void
    {
        $this->assertIsString(Str::vsprintfOrNull('Simple message without values', []));
    }

    /**
     * Demo for the mutation testing job, remove before merging: an edge case no other test has.
     */
    public function testNullValueIsFormattedAsEmptyString(): void
    {
        $this->assertSame('Value: ', Str::vsprintfOrNull('Value: %s', [null]));
    }

    public static function vsprintfOrNullDataProvider(): \Generator
    {
        yield [
            'Simple message without values',
            [],
            'Simple message without values',
        ];

        yield [
            'Message with a value: %s',
            ['value'],
            'Message with a value: value',
        ];

        // Demo for the mutation testing job, remove before merging: the same kind of input as the row above.
        yield [
            'Another message with a value: %s',
            ['other'],
            'Another message with a value: other',
        ];

        yield [
            'Message with placeholders but no values: %s',
            [],
            'Message with placeholders but no values: %s',
        ];

        yield [
            'Message with placeholders but incorrect number of values: %s, %s',
            ['value'],
            null,
        ];

        yield [
            'Message with placeholder: %s',
            [[1, 2, 3]],
            null,
        ];

        yield [
            'Message with placeholder: %s',
            [new \stdClass()],
            null,
        ];
    }
}

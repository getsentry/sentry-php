<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\OptionsResolver;

class OptionResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        StubLogger::$logs = [];
    }

    public function testFlatOptionResolve(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'foo' => 'bar',
            'test' => 10,
            'bla' => 'blu',
        ]);
        $result = $resolver->resolve(['foo' => 'example', 'test' => 200]);
        $this->assertEquals(['foo' => 'example', 'test' => 200, 'bla' => 'blu'], $result);
    }

    public function testNestedOptionResolve(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'foo' => [
                'bar' => [
                    'baz' => 'abc',
                    'example' => 'sweet',
                ],
            ],
            'a' => 'b',
        ]);

        $result = $resolver->resolve([
            'foo' => [
                'bar' => [
                    'baz' => 'hello',
                ],
            ],
        ]);

        $this->assertSame([
            'foo' => ['bar' => ['baz' => 'hello', 'example' => 'sweet']],
            'a' => 'b',
        ], $result);
    }

    public function testArrayValues(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => ['bar', 'baz'], 'a' => 'b']);
        $result = $resolver->resolve(['foo' => ['php'], 'a' => 'b']);
        $this->assertEquals(['foo' => ['php'], 'a' => 'b'], $result);
    }

    public function testEmptyDefaultArrayIsReplaced(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'empty' => [],
        ]);

        $result = $resolver->resolve([
            'empty' => ['not' => 'empty'],
        ]);

        $this->assertSame(['empty' => ['not' => 'empty']], $result);
    }

    public function testRegularArrayIsReplaced(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'list' => ['abc', 'efg'],
        ]);
        $result = $resolver->resolve([
            'list' => ['foo'],
        ]);
        $this->assertSame(['list' => ['foo']], $result);
    }

    public function testDefaultsAreNormalizedInSubtree(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setNormalizer('foo.bar.baz', static function (string $value): string {
            return trim($value);
        });
        $resolver->setDefault('foo', ['bar' => ['baz' => '   abc   ', 'example' => '   sweet   ']]);

        $result = $resolver->resolve();

        $this->assertSame([
            'foo' => [
                'bar' => [
                    'baz' => 'abc',
                    'example' => '   sweet   ',
                ],
            ],
        ], $result);
    }

    public function testNormalizerIsAppliedInSubtree(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setAllowedTypes('foo.bar.baz', 'string');
        $resolver->setNormalizer('foo.bar.baz', static function (string $value): string {
            return trim($value);
        });
        $resolver->setDefaults([
            'foo' => [
                'bar' => [
                    'baz' => 'foo',
                    'example' => 'sweet',
                ],
            ],
        ]);

        $result = $resolver->resolve([
            'foo' => [
                'bar' => [
                    'baz' => '   abc   ',
                ],
            ],
        ]);

        $this->assertSame([
            'foo' => [
                'bar' => [
                    'baz' => 'abc',
                    'example' => 'sweet',
                ],
            ],
        ], $result);
    }

    public function testNullIsValidDefault(): void
    {
        $logger = StubLogger::getInstance();
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['known' => null]);

        $result = $resolver->resolveOnly([
            'known' => null,
            'missing' => null,
        ], [], $logger);

        $this->assertSame(['known' => null], $result);
        $this->assertSame([[
            'level' => 'debug',
            'message' => 'Option "missing" does not exist and will be ignored',
            'context' => [],
        ]], StubLogger::$logs);
    }

    public function testResolveOnlyReplacesArrayValues(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'foo' => [
                'map' => [],
            ],
        ]);
        $currentOptions = [
            'foo' => [
                'map' => ['old' => 'value'],
            ],
        ];

        $result = $this->resolvePartialUpdate($resolver, $currentOptions, [
            'foo' => ['map' => ['new' => 'value']],
        ]);

        $this->assertSame([
            'foo' => [
                'map' => ['new' => 'value'],
            ],
        ], $result);
    }

    public function testResolveOnlyDoesNotNormalizeOmittedValues(): void
    {
        $normalizerCalls = 0;
        $resolver = new OptionsResolver();
        $resolver->setNormalizer('foo.bar.example', static function (string $value) use (&$normalizerCalls): string {
            ++$normalizerCalls;

            return $value;
        });
        $resolver->setDefaults([
            'foo' => [
                'bar' => [
                    'baz' => 'abc',
                    'example' => 'sweet',
                ],
            ],
        ]);
        $normalizerCalls = 0;

        $result = $resolver->resolveOnly([
            'foo' => ['bar' => ['baz' => 'hello']],
        ]);

        $this->assertSame([
            'foo' => ['bar' => ['baz' => 'hello', 'example' => 'sweet']],
        ], $result);
        $this->assertSame(0, $normalizerCalls);
    }

    public function testResolveOnlyKeepsCurrentValueWhenOverrideIsInvalid(): void
    {
        $logger = StubLogger::getInstance();
        $resolver = new OptionsResolver();
        $resolver->setAllowedTypes('foo.bar.baz', 'string');
        $resolver->setDefaults([
            'foo' => [
                'bar' => [
                    'baz' => 'default',
                ],
            ],
        ]);
        $currentOptions = [
            'foo' => [
                'bar' => [
                    'baz' => 'current',
                ],
            ],
        ];

        $result = $this->resolvePartialUpdate($resolver, $currentOptions, [
            'foo' => ['bar' => ['baz' => 42]],
        ], $logger);

        $this->assertSame($currentOptions, $result);
        $this->assertSame([[
            'level' => 'debug',
            'message' => 'Invalid value for option "foo.bar.baz". The value has been ignored.',
            'context' => [],
        ]], StubLogger::$logs);
    }

    public function testNestedValidationLogsFullOptionPaths(): void
    {
        $logger = StubLogger::getInstance();
        $resolver = new OptionsResolver();
        $resolver->setAllowedTypes('foo.bar.baz', 'string');
        $resolver->setDefaults([
            'foo' => [
                'bar' => [
                    'baz' => 'abc',
                    'example' => 'sweet',
                ],
            ],
        ]);

        $result = $resolver->resolve([
            'foo' => [
                'bar' => [
                    0 => 'ignored',
                    'unknown' => 'ignored',
                    'baz' => 42,
                    'example' => 'tart',
                ],
            ],
        ], $logger);

        $this->assertSame([
            'foo' => ['bar' => ['baz' => 'abc', 'example' => 'tart']],
        ], $result);
        $this->assertSame([
            [
                'level' => 'debug',
                'message' => 'Option "foo.bar.0" does not exist and will be ignored',
                'context' => [],
            ],
            [
                'level' => 'debug',
                'message' => 'Option "foo.bar.unknown" does not exist and will be ignored',
                'context' => [],
            ],
            [
                'level' => 'debug',
                'message' => 'Invalid value for option "foo.bar.baz". The value has been ignored.',
                'context' => [],
            ],
        ], StubLogger::$logs);
    }

    public function testNestedSchemaFallsBackWhenPassedValueIsNotAnArray(): void
    {
        $logger = StubLogger::getInstance();
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'foo' => [
                'bar' => [
                    'baz' => 'abc',
                    'example' => 'sweet',
                ],
            ],
        ]);

        $result = $resolver->resolve(['foo' => 'invalid'], $logger);

        $this->assertSame([
            'foo' => ['bar' => ['baz' => 'abc', 'example' => 'sweet']],
        ], $result);
        $this->assertSame([[
            'level' => 'debug',
            'message' => 'Invalid value for option "foo". The value has been ignored.',
            'context' => [],
        ]], StubLogger::$logs);
    }

    public function testAllowedValuesAcceptsScalarAtNestedPath(): void
    {
        $logger = StubLogger::getInstance();
        $resolver = new OptionsResolver();
        $resolver->setAllowedValues('foo.bar.baz', 'abc');
        $resolver->setDefaults([
            'foo' => ['bar' => ['baz' => 'abc']],
        ]);

        $result = $resolver->resolve(['foo' => ['bar' => ['baz' => 'abc']]], $logger);

        $this->assertSame([
            'foo' => ['bar' => ['baz' => 'abc']],
        ], $result);
        $this->assertSame([], StubLogger::$logs);

        $result = $resolver->resolve(['foo' => ['bar' => ['baz' => 'def']]], $logger);

        $this->assertSame([
            'foo' => ['bar' => ['baz' => 'abc']],
        ], $result);
        $this->assertSame([[
            'level' => 'debug',
            'message' => 'Invalid value for option "foo.bar.baz". The value has been ignored.',
            'context' => [],
        ]], StubLogger::$logs);
    }

    public function testNullAllowedValueDisablesNestedValueValidation(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setAllowedValues('foo.bar', null);
        $resolver->setDefaults([
            'foo' => ['bar' => null],
        ]);

        $result = $resolver->resolve([
            'foo' => ['bar' => 'custom'],
        ]);

        $this->assertSame([
            'foo' => ['bar' => 'custom'],
        ], $result);
    }

    public function testAllowedTypeIntValid(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => ['bar', 'baz'], 'a' => 20]);
        $resolver->setAllowedTypes('a', ['integer']);
        $result = $resolver->resolve(['foo' => ['bar', 'baz'], 'a' => 100]);
        $this->assertEquals(['foo' => ['bar', 'baz'], 'a' => 100], $result);
    }

    public function testAllowedTypeIntInvalid(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => ['bar', 'baz'], 'a' => 20]);
        $resolver->setAllowedTypes('a', ['integer']);
        $result = $resolver->resolve(['foo' => ['bar', 'baz'], 'a' => '100']);
        $this->assertEquals(['foo' => ['bar', 'baz'], 'a' => 20], $result);
    }

    /**
     * @dataProvider allowedTypeTestProvider
     */
    public function testAllowedTypes(array $defaults, array $options, array $allowedTypes, array $expectedResult): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults($defaults);
        foreach ($allowedTypes as $path => $type) {
            $resolver->setAllowedTypes($path, $type);
        }
        $result = $resolver->resolve($options);
        $this->assertEquals($result, $expectedResult);
    }

    /**
     * @dataProvider allowedValueTestProvider
     */
    public function testAllowedValues(array $defaults, array $options, array $allowedValues, array $expectedResult): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults($defaults);
        foreach ($allowedValues as $path => $values) {
            $resolver->setAllowedValues($path, $values);
        }
        $result = $resolver->resolve($options);
        $this->assertEquals($result, $expectedResult);
    }

    /**
     * @dataProvider normalizerTestProvider
     */
    public function testNormalizers(array $defaults, array $options, array $normalizers, array $expectedResult): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults($defaults);
        foreach ($normalizers as $path => $type) {
            $resolver->setNormalizer($path, $type);
        }
        $result = $resolver->resolve($options);
        $this->assertEquals($result, $expectedResult);
    }

    /**
     * @dataProvider resolveOnlyTestProvider
     */
    public function testResolveOnly(array $defaults, array $options, array $expectedResult): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults($defaults);
        $result = $resolver->resolveOnly($options);
        $this->assertEquals($expectedResult, $result);
    }

    public function testNormalizerReturnsInvalidType(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => 'bar']);
        $resolver->setAllowedTypes('foo', ['string']);
        $resolver->setNormalizer('foo', static function ($value) {
            return 8;
        });
        $result = $resolver->resolve(['foo' => 'test']);
        $this->assertEquals(['foo' => 'bar'], $result);
    }

    public function testNormalizerReturnsInvalidValue(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => 'b']);
        $resolver->setAllowedValues('foo', ['a', 'b', 'c']);
        $resolver->setNormalizer('foo', static function ($value) {
            return 'z';
        });
        $result = $resolver->resolve(['foo' => 'a']);
        $this->assertEquals(['foo' => 'b'], $result);
    }

    public function testNormalizerResultFailsValidation(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => 'b']);
        $resolver->setAllowedValues('foo', ['a', 'b', 'c']);
        $resolver->setNormalizer('foo', static function ($value) {
            return false;
        });
        $result = $resolver->resolve(['foo' => 'a']);
        $this->assertEquals(['foo' => 'b'], $result);
    }

    public function allowedTypeTestProvider(): \Generator
    {
        yield 'Integer allowed type passes validation' => [
            ['a' => 'b', 'c' => 10],
            ['c' => 20],
            ['c' => ['int']],
            ['a' => 'b', 'c' => 20],
        ];

        yield 'Integer allowed type fails validation and default value is used' => [
            ['a' => 'b', 'c' => 10],
            ['c' => 'foo'],
            ['c' => ['int']],
            ['a' => 'b', 'c' => 10],
        ];

        yield 'Float allowed type passes validation' => [
            ['a' => 'b', 'c' => 10.0],
            ['c' => 20.0],
            ['c' => ['float']],
            ['a' => 'b', 'c' => 20.0],
        ];

        yield 'Float allowed type fails validation and default value is used' => [
            ['a' => 'b', 'c' => 10.0],
            ['c' => 'foo'],
            ['c' => ['float']],
            ['a' => 'b', 'c' => 10.0],
        ];

        yield 'String allowed type passes validation' => [
            ['a' => 'b', 'c' => 'hello'],
            ['c' => 'world'],
            ['c' => ['string']],
            ['a' => 'b', 'c' => 'world'],
        ];

        yield 'String allowed type fails validation and default value is used' => [
            ['a' => 'b', 'c' => 'hello'],
            ['c' => 42],
            ['c' => ['string']],
            ['a' => 'b', 'c' => 'hello'],
        ];

        yield 'Boolean allowed type passes validation' => [
            ['a' => 'b', 'c' => true],
            ['c' => false],
            ['c' => ['bool']],
            ['a' => 'b', 'c' => false],
        ];

        yield 'Boolean allowed type fails validation and default value is used' => [
            ['a' => 'b', 'c' => true],
            ['c' => 'false'],
            ['c' => ['bool']],
            ['a' => 'b', 'c' => true],
        ];

        yield 'Array allowed type passes validation' => [
            ['a' => 'b', 'c' => ['foo' => 'bar']],
            ['c' => ['foo' => 'bar']],
            ['c' => ['array']],
            ['a' => 'b', 'c' => ['foo' => 'bar']],
        ];

        yield 'Array allowed type fails validation and default value is used' => [
            ['a' => 'b', 'c' => ['foo' => 'bar']],
            ['c' => 'test'],
            ['c' => ['array']],
            ['a' => 'b', 'c' => ['foo' => 'bar']],
        ];
    }

    public function allowedValueTestProvider(): \Generator
    {
        yield 'String in array of strings' => [
            ['a' => 'b'],
            ['a' => 'd'],
            ['a' => ['a', 'b', 'c', 'd']],
            ['a' => 'd'],
        ];

        yield 'String not in array of strings' => [
            ['a' => 'b'],
            ['a' => 'z'],
            ['a' => ['a', 'b', 'c', 'd']],
            ['a' => 'b'],
        ];

        yield 'Callback validates successfully' => [
            ['count' => 50],
            ['count' => 10],
            ['count' => static function ($value) {
                return $value >= 0 && $value <= 100;
            }],
            ['count' => 10],
        ];

        yield 'Callback validation fails' => [
            ['count' => 50],
            ['count' => 200],
            ['count' => static function ($value) {
                return $value >= 0 && $value <= 100;
            }],
            ['count' => 50],
        ];
    }

    public function resolveOnlyTestProvider(): \Generator
    {
        $defaults = ['foo' => 'bar', 'test' => 'example', 'a' => 'b'];

        yield 'Result only contains passed values' => [
            $defaults,
            ['foo' => 'test'],
            ['foo' => 'test'],
        ];

        yield 'Result does not contain invalid key' => [
            $defaults,
            ['foo' => 'test', 'example' => 'abcde'],
            ['foo' => 'test'],
        ];

        yield 'Empty input returns empty output' => [
            $defaults,
            [],
            [],
        ];
    }

    public function normalizerTestProvider(): \Generator
    {
        yield 'Normalizes successful' => [
            ['a' => 'b'],
            ['a' => '   c    '],
            ['a' => static function ($value) {
                return trim($value);
            }],
            ['a' => 'c'],
        ];
    }

    public function testDebugLogsProduced(): void
    {
        $logger = StubLogger::getInstance();
        $resolver = new OptionsResolver();
        $resolver->setAllowedValues('test', ['foo']);
        $resolver->setDefaults([
            'test' => 'foo',
        ]);
        $resolver->resolve(['example' => 'abc'], $logger);
        $this->assertSame([[
            'level' => 'debug',
            'message' => 'Option "example" does not exist and will be ignored',
            'context' => [],
        ]], StubLogger::$logs);

        $resolver->resolve(['test' => 'abc'], $logger);
        $this->assertSame([
            [
                'level' => 'debug',
                'message' => 'Option "example" does not exist and will be ignored',
                'context' => [],
            ],
            [
                'level' => 'debug',
                'message' => 'Invalid value for option "test". The value has been ignored.',
                'context' => [],
            ],
        ], StubLogger::$logs);
    }

    public function testDebugLogsProducedForResolveOnly(): void
    {
        $logger = StubLogger::getInstance();
        $resolver = new OptionsResolver();
        $resolver->setAllowedValues('test', ['foo']);
        $resolver->setDefaults(['test' => 'foo', 'abc' => 'def', 'bar' => 'baz']);

        $resolver->resolveOnly(['example' => 'test'], [], $logger);
        $this->assertSame([[
            'level' => 'debug',
            'message' => 'Option "example" does not exist and will be ignored',
            'context' => [],
        ]], StubLogger::$logs);

        $resolver->resolveOnly(['test' => 'abc'], [], $logger);
        $this->assertSame([
            [
                'level' => 'debug',
                'message' => 'Option "example" does not exist and will be ignored',
                'context' => [],
            ],
            [
                'level' => 'debug',
                'message' => 'Invalid value for option "test". The value has been ignored.',
                'context' => [],
            ],
        ], StubLogger::$logs);
    }

    /**
     * @param array<string, mixed> $currentOptions
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function resolvePartialUpdate(
        OptionsResolver $resolver,
        array $currentOptions,
        array $override,
        ?LoggerInterface $logger = null
    ): array {
        $resolved = $resolver->resolveOnly($override, $currentOptions, $logger);

        return array_merge($currentOptions, $resolved);
    }
}

<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Sentry\OptionsResolver;

class OptionResolverTest extends TestCase
{
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
        $resolver->setDefaults(['foo' => ['bar' => 'baz'], 'a' => 'b']);
        $result = $resolver->resolve(['foo' => ['bar' => 'test']]);
        $this->assertEquals(['foo' => ['bar' => 'test'], 'a' => 'b'], $result);
    }

    public function testArrayValues(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => ['bar', 'baz'], 'a' => 'b']);
        $result = $resolver->resolve(['foo' => ['php'], 'a' => 'b']);
        $this->assertEquals(['foo' => ['php'], 'a' => 'b'], $result);
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

    public function testNormalizerReturnsInvalidType()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => 'bar']);
        $resolver->setAllowedTypes('foo', ['string']);
        $resolver->setNormalizer('foo', function ($value) {
            return 8;
        });
        $result = $resolver->resolve(['foo' => 'test']);
        $this->assertEquals(['foo' => 'bar'], $result);
    }

    public function testNormalizerReturnsInvalidValue()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => 'b']);
        $resolver->setAllowedValues('foo', ['a', 'b', 'c']);
        $resolver->setNormalizer('foo', function ($value) {
            return 'z';
        });
        $result = $resolver->resolve(['foo' => 'a']);
        $this->assertEquals(['foo' => 'b'], $result);
    }

    public function testNormalizerResultFailsValidation()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['foo' => 'b']);
        $resolver->setAllowedValues('foo', ['a', 'b', 'c']);
        $resolver->setNormalizer('foo', function ($value) {
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
            ['count' => function ($value) {
                return $value >= 0 && $value <= 100;
            }],
            ['count' => 10],
        ];

        yield 'Callback validation fails' => [
            ['count' => 50],
            ['count' => 200],
            ['count' => function ($value) {
                return $value >= 0 && $value <= 100;
            }],
            ['count' => 50],
        ];
    }

    public function normalizerTestProvider()
    {
        yield 'Normalizes successful' => [
            ['a' => 'b'],
            ['a' => '   c    '],
            ['a' => function ($value) {
                return trim($value);
            }],
            ['a' => 'c'],
        ];
    }

    public function testDebugLogsProduced()
    {
        $logger = new class extends AbstractLogger {
            private $logs = [];

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = $message;
            }

            public function getLogs(): array
            {
                return $this->logs;
            }
        };
        $resolver = new OptionsResolver();
        $resolver->setAllowedValues('test', ['foo']);
        $resolver->setDefaults([
            'test' => 'foo',
        ]);
        $resolver->resolve(['example' => 'abc'], $logger);
        $this->assertCount(1, $logger->getLogs());
        $this->assertEquals('Option "example" does not exist and will be ignored', $logger->getLogs()[0]);

        $resolver->resolve(['test' => 'abc'], $logger);
        $this->assertCount(2, $logger->getLogs());
        $this->assertEquals('Invalid value for option "test". Using default value.', $logger->getLogs()[1]);
    }
}

<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

final class DataCollectionOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $dataCollection = new DataCollectionOptions();

        $this->assertTrue($dataCollection->shouldCollectUserInfo());
        $this->assertSame('denyList', $dataCollection->getCookies()->getMode());
        $this->assertSame([], $dataCollection->getCookies()->getTerms());
        $this->assertSame('denyList', $dataCollection->getHttpHeaders()->getRequest()->getMode());
        $this->assertSame('denyList', $dataCollection->getHttpHeaders()->getResponse()->getMode());
        $this->assertSame(DataCollectionOptions::HTTP_BODY_TYPES, $dataCollection->getHttpBodies());
        $this->assertSame('denyList', $dataCollection->getQueryParams()->getMode());
        $this->assertTrue($dataCollection->getGenAi()->getInputs());
        $this->assertTrue($dataCollection->getGenAi()->getOutputs());
        $this->assertTrue($dataCollection->shouldCollectStackFrameVariables());
        $this->assertSame(5, $dataCollection->getFrameContextLines());
    }

    public function testSetHttpHeadersUsesConstructorNormalization(): void
    {
        $httpHeaders = [
            'mode' => 'allowList',
            'terms' => ['x-request-id'],
        ];
        $fromConstructor = new DataCollectionOptions([
            'http_headers' => $httpHeaders,
        ]);
        $fromSetter = (new DataCollectionOptions())->setHttpHeaders($httpHeaders);

        $this->assertSame($fromConstructor->getHttpHeaders()->getRequest()->getMode(), $fromSetter->getHttpHeaders()->getRequest()->getMode());
        $this->assertSame($fromConstructor->getHttpHeaders()->getRequest()->getTerms(), $fromSetter->getHttpHeaders()->getRequest()->getTerms());
        $this->assertSame($fromConstructor->getHttpHeaders()->getResponse()->getMode(), $fromSetter->getHttpHeaders()->getResponse()->getMode());
        $this->assertSame($fromConstructor->getHttpHeaders()->getResponse()->getTerms(), $fromSetter->getHttpHeaders()->getResponse()->getTerms());
    }

    public function testHttpHeadersConfigurationAppliesToBothRequestAndResponse(): void
    {
        $dataCollection = new DataCollectionOptions([
            'http_headers' => [
                'mode' => 'allowList',
                'terms' => ['x-request-id'],
            ],
        ]);

        $this->assertSame('allowList', $dataCollection->getHttpHeaders()->getRequest()->getMode());
        $this->assertSame(['x-request-id'], $dataCollection->getHttpHeaders()->getRequest()->getTerms());
        $this->assertSame('allowList', $dataCollection->getHttpHeaders()->getResponse()->getMode());
        $this->assertSame(['x-request-id'], $dataCollection->getHttpHeaders()->getResponse()->getTerms());
    }

    public function testNullHttpBodiesUsesDefaultBodyTypes(): void
    {
        $dataCollection = new DataCollectionOptions([
            'http_bodies' => null,
        ]);

        $this->assertSame(DataCollectionOptions::HTTP_BODY_TYPES, $dataCollection->getHttpBodies());
    }

    /**
     * @dataProvider invalidOptionsDataProvider
     *
     * @param array<string, mixed> $options
     */
    public function testValidatesOptions(array $options): void
    {
        $this->expectException(InvalidOptionsException::class);

        new DataCollectionOptions($options);
    }

    public static function invalidOptionsDataProvider(): \Generator
    {
        yield 'invalid http body type' => [
            [
                'http_bodies' => ['invalid'],
            ],
        ];

        yield 'negative frame context lines' => [
            [
                'frame_context_lines' => -1,
            ],
        ];

        yield 'invalid key-value mode' => [
            [
                'cookies' => [
                    'mode' => 'invalid',
                ],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Sentry\Tests\Callbacks;

use PHPUnit\Framework\TestCase;
use Sentry\Callbacks\Sanitize;
use Sentry\Event;
use Sentry\EventFactory;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\Serializer;
use Sentry\Serializer\SerializerInterface;
use Sentry\Stacktrace;
use Exception;

final class SanitizeTest extends TestCase
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var RepresentationSerializer
     */
    private $representationSerializer;

    protected function setUp(): void
    {
        $this->options = new Options();
        $this->serializer = new Serializer($this->options);
        $this->representationSerializer = new RepresentationSerializer($this->options);
    }

    public function testRemoveApiToken(): void
    {
        $eventFactory = new EventFactory(
            $this->serializer,
            $this->representationSerializer,
            new Options(),
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $message = 'Unknown error (https://test.com?test1=1&apiToken=test_token&test2=2';
        $exception = new Exception($message);

        $stacktrace = new Stacktrace($this->options, $this->serializer, $this->representationSerializer);

        $stacktrace->addFrame('path/to/file', 1, ['file' => 'path/to/file', 'line' => 1, 'function' => 'testFunction',
            'args' => [
                'apiToken' => 'test_token',
            ],
        ]);

        $event = $eventFactory->create([
            'exception'  => $exception,
            'message'    => $message,
            'stacktrace' => $stacktrace,
        ]);

        /** @var Event $sanitizedEvent */
        $sanitizedEvent = call_user_func(new Sanitize(['apiToken']), $event);

        $this->assertEquals([
            'apiToken' => Sanitize::SANITIZED_STRING,
        ], $sanitizedEvent->getStacktrace()->getFrames()[0]->getVars());

        $exception = $event->getExceptions()[0];

        $this->assertEquals('Unknown error (https://test.com?test1=1&apiToken=' .
            Sanitize::SANITIZED_STRING . '&test2=2', $exception['value']);

        $this->assertEquals('Unknown error (https://test.com?test1=1&apiToken=' .
            Sanitize::SANITIZED_STRING . '&test2=2', $event->getMessage());
    }
}

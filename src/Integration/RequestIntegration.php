<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Sentry\Event;
use Sentry\Exception\JsonException;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Util\JSON;
use Zend\Diactoros\ServerRequestFactory;

/**
 * This integration collects information from the request and attaches them to
 * the event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RequestIntegration implements IntegrationInterface
{
    /**
     * This constant represents the size limit in bytes beyond which the body
     * of the request is not captured when the `max_request_body_size` option
     * is set to `small`.
     */
    private const REQUEST_BODY_SMALL_MAX_CONTENT_LENGTH = 10 ** 3;

    /**
     * This constant represents the size limit in bytes beyond which the body
     * of the request is not captured when the `max_request_body_size` option
     * is set to `medium`.
     */
    private const REQUEST_BODY_MEDIUM_MAX_CONTENT_LENGTH = 10 ** 4;

    /**
     * @var Options The client options
     */
    private $options;

    /**
     * Constructor.
     *
     * @param Options $options The client options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event): Event {
            $self = Hub::getCurrent()->getIntegration(self::class);

            if (!$self instanceof self) {
                return $event;
            }

            self::applyToEvent($self, $event);

            return $event;
        });
    }

    /**
     * Applies the information gathered by the this integration to the event.
     *
     * @param self                        $self    The current instance of the integration
     * @param Event                       $event   The event that will be enriched with a request
     * @param ServerRequestInterface|null $request The Request that will be processed and added to the event
     */
    public static function applyToEvent(self $self, Event $event, ?ServerRequestInterface $request = null): void
    {
        if (null === $request) {
            $request = isset($_SERVER['REQUEST_METHOD']) && \PHP_SAPI !== 'cli' ? ServerRequestFactory::fromGlobals() : null;
        }

        if (null === $request) {
            return;
        }

        $requestData = [
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
        ];

        if ($request->getUri()->getQuery()) {
            $requestData['query_string'] = $request->getUri()->getQuery();
        }

        if ($self->options->shouldSendDefaultPii()) {
            if ($request->hasHeader('REMOTE_ADDR')) {
                $requestData['env']['REMOTE_ADDR'] = $request->getHeaderLine('REMOTE_ADDR');
            }

            $requestData['cookies'] = $request->getCookieParams();
            $requestData['headers'] = $request->getHeaders();

            $userContext = $event->getUserContext();

            if (null === $userContext->getIpAddress() && $request->hasHeader('REMOTE_ADDR')) {
                $userContext->setIpAddress($request->getHeaderLine('REMOTE_ADDR'));
            }
        } else {
            $requestData['headers'] = $self->removePiiFromHeaders($request->getHeaders());
        }

        $requestBody = $self->captureRequestBody($request);

        if (!empty($requestBody)) {
            $requestData['data'] = $requestBody;
        }

        $event->setRequest($requestData);
    }

    /**
     * Removes headers containing potential PII.
     *
     * @param array $headers Array containing request headers
     *
     * @return array
     */
    private function removePiiFromHeaders(array $headers): array
    {
        $keysToRemove = ['authorization', 'cookie', 'set-cookie', 'remote_addr'];

        return array_filter(
            $headers,
            static function (string $key) use ($keysToRemove): bool {
                return !\in_array(strtolower($key), $keysToRemove, true);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Gets the decoded body of the request, if available. If the Content-Type
     * header contains "application/json" then the content is decoded and if
     * the parsing fails then the raw data is returned. If there are submitted
     * fields or files, all of their information are parsed and returned.
     *
     * @param ServerRequestInterface $serverRequest The server request
     *
     * @return mixed
     */
    private function captureRequestBody(ServerRequestInterface $serverRequest)
    {
        $maxRequestBodySize = $this->options->getMaxRequestBodySize();
        $requestBody = $serverRequest->getBody();

        if (
            'none' === $maxRequestBodySize ||
            ('small' === $maxRequestBodySize && $requestBody->getSize() > self::REQUEST_BODY_SMALL_MAX_CONTENT_LENGTH) ||
            ('medium' === $maxRequestBodySize && $requestBody->getSize() > self::REQUEST_BODY_MEDIUM_MAX_CONTENT_LENGTH)
        ) {
            return null;
        }

        $requestData = $serverRequest->getParsedBody();
        $requestData = \is_array($requestData) ? $requestData : [];

        foreach ($serverRequest->getUploadedFiles() as $fieldName => $uploadedFiles) {
            if (!\is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            /** @var UploadedFileInterface $uploadedFile */
            foreach ($uploadedFiles as $uploadedFile) {
                $requestData[$fieldName][] = [
                    'client_filename' => $uploadedFile->getClientFilename(),
                    'client_media_type' => $uploadedFile->getClientMediaType(),
                    'size' => $uploadedFile->getSize(),
                ];
            }
        }

        if (!empty($requestData)) {
            return $requestData;
        }

        if ('application/json' === $serverRequest->getHeaderLine('Content-Type')) {
            try {
                return JSON::decode($requestBody->getContents());
            } catch (JsonException $exception) {
                // Fallback to returning the raw data from the request body
            }
        }

        return $requestBody->getContents();
    }
}

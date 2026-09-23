<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\Options;
use Sentry\State\HubInterface;

/**
 * The policy acts as a container to deal with legacy and new data collection options.
 * Checking both options is often tedious and very repetitive and will also make removal
 * more difficult in the future.
 *
 * This class is meant to make it simpler to deal with both competing values and should make
 * retiring the legacy option easier
 */
final class DataCollectionPolicy
{
    public const MAX_HTTP_BODY_LENGTH = 100000;

    private const MAX_REQUEST_BODY_LENGTH = [
        'none' => 0,
        'never' => 0,
        'small' => 1000,
        'medium' => 10000,
        'always' => self::MAX_HTTP_BODY_LENGTH,
    ];

    /**
     * @var Options|null
     */
    private $options;

    private function __construct(?Options $options)
    {
        $this->options = $options;
    }

    public static function fromHub(HubInterface $hub): self
    {
        $client = $hub->getClient();

        return new self($client === null ? null : $client->getOptions());
    }

    public static function fromOptions(?Options $options): self
    {
        return new self($options);
    }

    public function getOptions(): ?Options
    {
        return $this->options;
    }

    public function getDataCollection(): ?DataCollectionOptions
    {
        return $this->options === null ? null : $this->options->getDataCollection();
    }

    public function isLegacyMode(): bool
    {
        return $this->getDataCollection() === null;
    }

    public function getHttpBodyLimit(HttpMessageType $messageType): ?int
    {
        $collection = $this->getDataCollection();
        if ($collection === null || !$collection->shouldCollectHttpBody($messageType)) {
            return null;
        }

        return $this->calculateMaxHttpBodyLength($messageType) ?: null;
    }

    public function getLegacyRequestBodyLimit(): ?int
    {
        if (!$this->isLegacyMode()) {
            return null;
        }

        return $this->calculateMaxHttpBodyLength(HttpMessageType::incomingRequest()) ?: null;
    }

    public function shouldCollectUserInfo(): bool
    {
        $dataCollection = $this->getDataCollection();

        if ($dataCollection !== null) {
            return $dataCollection->shouldCollectUserInfo();
        }

        return $this->options !== null && $this->options->shouldSendDefaultPii();
    }

    /**
     * Returns the number of source code lines to include above and below each stack frame,
     * or `null` if no source code context should be collected.
     */
    public function getFrameContextLines(): ?int
    {
        $dataCollection = $this->getDataCollection();

        if ($dataCollection !== null) {
            return $dataCollection->getFrameContextLines();
        }

        return $this->options === null ? null : $this->options->getContextLines();
    }

    private function calculateMaxHttpBodyLength(HttpMessageType $messageType): int
    {
        if ($this->options === null) {
            return 0;
        }

        if ($messageType->isRequest()) {
            $maxRequestBodySize = $this->options->getMaxRequestBodySize();

            // Preserve legacy behaviour to capture request bodies unbounded
            if ($this->isLegacyMode() && $maxRequestBodySize === 'always') {
                return -1;
            }

            return self::MAX_REQUEST_BODY_LENGTH[$maxRequestBodySize] ?? 0;
        }

        return self::MAX_HTTP_BODY_LENGTH;
    }
}

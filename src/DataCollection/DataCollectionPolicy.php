<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\Options;
use Sentry\State\HubInterface;

/**
 * Provides a live view of the effective data collection configuration.
 *
 * A null data_collection option intentionally selects the legacy
 * send_default_pii behavior until the next major-version migration.
 *
 * This is shared infrastructure for first-party SDK integrations. It is
 * public in PHP terms so framework SDKs can use it without internal-API
 * diagnostics, but it is not a primary end-user configuration surface.
 */
final class DataCollectionPolicy
{
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

    public function shouldCollectUserInfo(): bool
    {
        $dataCollection = $this->getDataCollection();

        if ($dataCollection !== null) {
            return $dataCollection->shouldCollectUserInfo();
        }

        return $this->options !== null && $this->options->shouldSendDefaultPii();
    }
}

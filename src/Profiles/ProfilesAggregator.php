<?php

declare(strict_types=1);

namespace Sentry\Profiles;

use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;

/**
 * @internal
 */
final class ProfilesAggregator
{
    /**
     * @var \ExcimerLog[]
     */
    private $excimerLogs = [];

    /**
     * @var float|null The start time of the profile as a Unix timestamp with microseconds
     */
    private $startTimeStamp;

    /**
     * @var string|null The profiler ID
     */
    private $profilerId;

    /**
     * @param \ExcimerLog $excimerLog
     */
    public function add($excimerLog): void
    {
        $this->excimerLogs[] = $excimerLog;
    }

    public function setStartTimeStamp(?float $startTimeStamp): void
    {
        $this->startTimeStamp = $startTimeStamp;
    }

    public function setProfilerId(?string $profilerId): void
    {
        $this->profilerId = $profilerId;
    }

    public function flush(): ?EventId
    {
        if (empty($this->excimerLogs)) {
            return null;
        }

        $profileChunk = new ProfileChunk();
        $profileChunk->setExcimerLogs($this->excimerLogs);
        $profileChunk->setStartTimeStamp($this->startTimeStamp);
        $profileChunk->setProfilerId($this->profilerId);

        $hub = SentrySdk::getCurrentHub();
        $event = Event::createProfileChunk()->setProfileChunk($profileChunk);

        $this->excimerLogs = [];

        return $hub->captureEvent($event);
    }
}

<?php

declare(strict_types=1);

namespace Sentry;

final class MonitorConfig
{
    /**
     * @var MonitorSchedule The schedule of the monitor
     */
    private $schedule;

    /**
     * @var int|null The check-in margin in seconds
     */
    private $checkinMargin;

    /**
     * @var int|null The maximum runtime in seconds
     */
    private $maxRuntime;

    /**
     * @var string|null The timezone
     */
    private $timezone;

    public function __construct(
        MonitorSchedule $schedule,
        ?int $checkinMargin = null,
        ?int $maxRuntime = null,
        ?string $timezone = null
    ) {
        $this->schedule = $schedule;
        $this->checkinMargin = $checkinMargin;
        $this->maxRuntime = $maxRuntime;
        $this->timezone = $timezone;
    }

    public function getShedule(): MonitorSchedule
    {
        return $this->schedule;
    }

    public function setSchedule(MonitorSchedule $schedule): void
    {
        $this->schedule = $schedule;
    }

    public function getCheckinMargin(): ?int
    {
        return $this->checkinMargin;
    }

    public function setCheckinMargin(?int $checkinMargin): void
    {
        $this->checkinMargin = $checkinMargin;
    }

    public function getMaxRuntime(): ?int
    {
        return $this->maxRuntime;
    }

    public function setMaxRuntime(?int $maxRuntime): void
    {
        $this->maxRuntime = $maxRuntime;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schedule' => $this->schedule->toArray(),
            'checkin_margin' => $this->checkinMargin,
            'max_runtime' => $this->maxRuntime,
            'timezone' => $this->timezone,
        ];
    }
}

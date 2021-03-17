<?php

declare(strict_types=1);

namespace Sentry\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * This Monolog handler logs every message as a {@see Breadcrumb} into the current {@see Scope},
 * to enrich any event sent to Sentry.
 */
final class BreadcrumbHandler extends AbstractProcessingHandler
{
    /**
     * @var HubInterface
     */
    private $hub;

    /**
     * @param HubInterface $hub    The hub to which errors are reported
     * @param int|string   $level  The minimum logging level at which this
     *                             handler will be triggered
     * @param bool         $bubble Whether the messages that are handled can
     *                             bubble up the stack or not
     */
    public function __construct(HubInterface $hub, $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->hub = $hub;

        parent::__construct($level, $bubble);
    }

    /**
     * @psalm-suppress MoreSpecificImplementedParamType
     *
     * @param array{
     *      level: int,
     *      channel: string,
     *      datetime: \DateTimeImmutable,
     *      message: string,
     *      formatted?: string,
     *      extra?: array<string, mixed>
     * } $record {@see https://github.com/Seldaek/monolog/blob/main/doc/message-structure.md}
     */
    protected function write(array $record): void
    {
        $breadcrumb = new Breadcrumb(
            $this->getBreadcrumbLevel($record['level']),
            $this->getBreadcrumbType($record['level']),
            $record['channel'],
            $record['formatted'] ?? $record['message'],
            $record['extra'] ?? [],
            $record['datetime']->getTimestamp()
        );

        $this->hub->addBreadcrumb($breadcrumb);
    }

    private function getBreadcrumbLevel(int $level): string
    {
        switch ($level) {
            case Logger::DEBUG:
                return Breadcrumb::LEVEL_DEBUG;
            case Logger::INFO:
            case Logger::NOTICE:
                return Breadcrumb::LEVEL_INFO;
            case Logger::WARNING:
                return Breadcrumb::LEVEL_WARNING;
            case Logger::ERROR:
                return Breadcrumb::LEVEL_ERROR;
            default:
                return Breadcrumb::LEVEL_FATAL;
        }
    }

    private function getBreadcrumbType(int $level): string
    {
        if ($level >= Logger::ERROR) {
            return Breadcrumb::TYPE_ERROR;
        }

        return Breadcrumb::TYPE_DEFAULT;
    }
}

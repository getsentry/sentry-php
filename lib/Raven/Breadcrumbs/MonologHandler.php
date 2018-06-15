<?php

namespace Raven\Breadcrumbs;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Raven\Client;

class MonologHandler extends AbstractProcessingHandler
{
    /**
     * Translates Monolog log levels to Raven log levels.
     */
    protected $logLevels = [
        Logger::DEBUG => Client::LEVEL_DEBUG,
        Logger::INFO => Client::LEVEL_INFO,
        Logger::NOTICE => Client::LEVEL_INFO,
        Logger::WARNING => Client::LEVEL_WARNING,
        Logger::ERROR => Client::LEVEL_ERROR,
        Logger::CRITICAL => Client::LEVEL_FATAL,
        Logger::ALERT => Client::LEVEL_FATAL,
        Logger::EMERGENCY => Client::LEVEL_FATAL,
    ];

    protected $excMatch = '/^exception \'([^\']+)\' with message \'(.+)\' in .+$/s';

    /**
     * @var \Raven\Client the client object that sends the message to the server
     */
    protected $ravenClient;

    /**
     * @param Client $ravenClient The Raven client
     * @param int    $level       The minimum logging level at which this handler will be triggered
     * @param bool   $bubble      Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Client $ravenClient, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->ravenClient = $ravenClient;
    }

    /**
     * @param string $message
     *
     * @return array|null
     */
    protected function parseException($message)
    {
        if (preg_match($this->excMatch, $message, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        // sentry uses the 'nobreadcrumb' attribute to skip reporting
        if (!empty($record['context']['nobreadcrumb'])) {
            return;
        }

        if (
            isset($record['context']['exception'])
            && (
                $record['context']['exception'] instanceof \Exception
                || (\PHP_VERSION_ID >= 70000 && $record['context']['exception'] instanceof \Throwable)
            )
        ) {
            /**
             * @var \Exception|\Throwable
             */
            $exc = $record['context']['exception'];

            /** @noinspection PhpUndefinedMethodInspection */
            $breadcrumb = new Breadcrumb($this->logLevels[$record['level']], Breadcrumb::TYPE_ERROR, $record['channel'], null, [
                'type' => get_class($exc),
                'value' => $exc->getMessage(),
            ]);

            $this->ravenClient->leaveBreadcrumb($breadcrumb);
        } else {
            // TODO(dcramer): parse exceptions out of messages and format as above
            if ($error = $this->parseException($record['message'])) {
                $breadcrumb = new Breadcrumb($this->logLevels[$record['level']], Breadcrumb::TYPE_ERROR, $record['channel'], null, [
                    'type' => $error[0],
                    'value' => $error[1],
                ]);

                $this->ravenClient->leaveBreadcrumb($breadcrumb);
            } else {
                $breadcrumb = new Breadcrumb($this->logLevels[$record['level']], Breadcrumb::TYPE_ERROR, $record['channel'], $record['message']);

                $this->ravenClient->leaveBreadcrumb($breadcrumb);
            }
        }
    }
}

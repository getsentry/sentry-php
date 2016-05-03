<?php

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class Raven_Breadcrumbs_MonologHandler extends AbstractProcessingHandler
{
    /**
     * Translates Monolog log levels to Raven log levels.
     */
    private $logLevels = array(
        Logger::DEBUG     => Raven_Client::DEBUG,
        Logger::INFO      => Raven_Client::INFO,
        Logger::NOTICE    => Raven_Client::INFO,
        Logger::WARNING   => Raven_Client::WARNING,
        Logger::ERROR     => Raven_Client::ERROR,
        Logger::CRITICAL  => Raven_Client::FATAL,
        Logger::ALERT     => Raven_Client::FATAL,
        Logger::EMERGENCY => Raven_Client::FATAL,
    );

    /**
     * @var Raven_Client the client object that sends the message to the server
     */
    protected $ravenClient;

    /**
     * @param Raven_Client $ravenClient
     * @param int          $level       The minimum logging level at which this handler will be triggered
     * @param Boolean      $bubble      Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Raven_Client $ravenClient, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->ravenClient = $ravenClient;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        // TODO(dcramer): support $record['context']['exception'] instanceof \Exception
        $crumb = array(
            'level' => $this->logLevels[$record['level']],
            'category' => $record['channel'],
            'message' => $record['message'],
        );

        $this->ravenClient->breadcrumbs->record($crumb);
    }
}

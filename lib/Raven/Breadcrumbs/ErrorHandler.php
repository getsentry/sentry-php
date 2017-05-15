<?php

namespace Raven\Breadcrumbs;

class ErrorHandler
{
    protected $existingHandler;

    /**
     * @var \Raven\Client the client object that sends the message to the server
     */
    protected $ravenClient;

    /**
     * @param \Raven\Client $ravenClient
     */
    public function __construct(\Raven\Client $ravenClient)
    {
        $this->ravenClient = $ravenClient;
    }

    public function handleError($code, $message, $file = '', $line = 0, $context = [])
    {
        $this->ravenClient->getBreadcrumbs()->record([
            'category' => 'error_reporting',
            'message' => $message,
            'level' => $this->ravenClient->translateSeverity($code),
            'data' => [
                'code' => $code,
                'line' => $line,
                'file' => $file,
            ],
        ]);

        if ($this->existingHandler !== null) {
            return call_user_func($this->existingHandler, $code, $message, $file, $line, $context);
        } else {
            return false;
        }
    }

    public function install()
    {
        $this->existingHandler = set_error_handler([$this, 'handleError'], E_ALL);
        return $this;
    }
}

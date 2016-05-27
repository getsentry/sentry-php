<?php

class Raven_Breadcrumbs_ErrorHandler
{
    private $existingHandler;

    /**
     * @var Raven_Client the client object that sends the message to the server
     */
    protected $ravenClient;

    /**
     * @param Raven_Client $ravenClient
     * @param int          $level       The minimum logging level at which this handler will be triggered
     * @param Boolean      $bubble      Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Raven_Client $ravenClient)
    {
        $this->ravenClient = $ravenClient;
    }

    public function codeToName($code)
    {
        switch ($code) {
            case 1:
                return 'E_ERROR';
            case 2:
                return 'E_WARNING';
            case 4:
                return 'E_PARSE';
            case 8:
                return 'E_NOTICE';
            case 16:
                return 'E_CORE_ERROR';
            case 32:
                return 'E_CORE_WARNING';
            case 64:
                return 'E_COMPILE_ERROR';
            case 128:
                return 'E_COMPILE_WARNING';
            case 256:
                return 'E_USER_ERROR';
            case 512:
                return 'E_USER_WARNING';
            case 1024:
                return 'E_USER_NOTICE';
            case 2048:
                return 'E_STRICT';
            case 4096:
                return 'E_RECOVERABLE_ERROR';
            case 8192:
                return 'E_DEPRECATED';
            case 16384:
                return 'E_USER_DEPRECATED';
            case 30719:
                return 'E_ALL';
            default:
                return 'E_UNKNOWN';
        }
    }

    public function codeToLevel($code)
    {
        switch ($code) {
            case 1:
            case 4:
            case 16:
            case 64:
            case 256:
            case 4096:
                return 'error';
            case 2:
            case 32:
            case 128:
            case 512:
                return 'warning';
            default:
                return 'info';
        }
    }

    public function handleError($code, $message, $file = '', $line = 0, $context=array())
    {
        $this->ravenClient->breadcrumbs->record(array(
            'category' => 'error_reporting',
            'message' => $message,
            'level' => $this->codeToLevel($code),
            'data' => array(
                'code' => $code,
                'line' => $line,
                'file' => $file,
            ),
        ));

        if ($this->existingHandler !== null) {
            return call_user_func($this->existingHandler, $code, $message, $file, $line, $context);
        } else {
            return false;
        }
    }

    public function install()
    {
        $this->existingHandler = set_error_handler(array($this, 'handleError'), E_ALL);
        return $this;
    }
}

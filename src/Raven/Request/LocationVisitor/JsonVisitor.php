<?php

namespace Raven\Request\LocationVisitor;

use Guzzle\Http\Message\RequestInterface;
use Guzzle\Service\Command\CommandInterface;
use Guzzle\Service\Command\LocationVisitor\Request\JsonVisitor as BaseJsonVisitor;
use Guzzle\Service\Description\Parameter;

class JsonVisitor extends BaseJsonVisitor
{
    public function after(CommandInterface $command, RequestInterface $request)
    {
        if (isset($this->data[$command]) && is_array($this->data[$command])) {
            $json = $this->data[$command];
            array_walk_recursive($json, array(__CLASS__, 'sanitize'));
            $this->data[$command] = $json;
        }

        parent::after($command, $request);
    }

    // Code from the original raven client

    private static $mask = '********';
    private static $fieldsToHideRegex = '/(authorization|password|passwd|secret|password_confirmation|card_number)/i';
    private static $valuesToHideRegex = '/^(?:\d[ -]*?){13,16}$/';

    public static function sanitize(&$item, $key)
    {
        if (empty($item)) {
            return;
        }

        if (preg_match(self::$valuesToHideRegex, $item)) {
            $item = self::$mask;
        }

        if (empty($key)) {
            return;
        }

        if (preg_match(self::$fieldsToHideRegex, $key)) {
            $item = self::$mask;
        }
    }
}

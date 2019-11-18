<?php

namespace Sentry\Callbacks;

use ReflectionProperty;
use Sentry\Event;
use Sentry\Frame;
use Sentry\Stacktrace;

class Sanitize implements BeforeSendCallback
{
    protected $words;

    const SANITIZED_STRING = '**********';

    public function __construct(array $words)
    {
        $this->words = $words;
    }

    public function __invoke(Event $event)
    {
        foreach ($this->words as $word) {
            $regexps = [
                "/{$word}=[^&]*/" => $word . '=' . self::SANITIZED_STRING,
            ];

            $event->setExceptions($this->sanitizeExceptions($event->getExceptions(), $word, $regexps));
            $event->setMessage(self::sanitizeString($event->getMessage(), $regexps));
            self::sanitizeStacktrace($event->getStacktrace(), $word, $regexps);
        }

        return $event;
    }

    protected static function sanitizeExceptions(array $exceptions, string $word, array $regexps)
    {
        foreach ($exceptions as &$exception) {
            foreach ($regexps as $regexp => $replace) {
                $exception['value'] = self::sanitizeString($exception['value'], $regexps);
            }

            self::sanitizeStacktrace($exception['stacktrace'], $word, $regexps);
        }

        return $exceptions;
    }

    protected static function sanitizeStacktrace(Stacktrace $stacktrace, string $word, array $regexps)
    {
        $frames = $stacktrace->getFrames();

        for ($i = count($frames) - 1; $i >= 0; --$i) {
            $stacktrace->removeFrame($i);
        }

        foreach ($frames as $idx => $frame) {
            $vars = $frame->getVars();

            self::recursiveSanitizeVar($vars, $word, $regexps);

            $frame->setVars($vars);
        }

        self::updateFrames($stacktrace, ...$frames);
    }

    private static function sanitizeString($value, array $regexps)
    {
        foreach ($regexps as $regexp => $replace) {
            $value = preg_replace($regexp, $replace, $value);
        }

        return $value;
    }

    private static function recursiveSanitizeVar(&$var, string $word, array $regexps)
    {
        if (is_array($var)) {
            foreach ($var as $key => &$value) {
                if ($key === $word && is_string($value)) {
                    $value = self::SANITIZED_STRING;
                } else {
                    $value = self::recursiveSanitizeVar($value, $word, $regexps);
                }
            }
        }

        if (is_string($var)) {
            foreach ($regexps as $regexp => $replace) {
                $var = preg_replace($regexp, $replace, $var);
            }
        }

        return $var;
    }

    private static function updateFrames(Stacktrace $stacktrace, Frame ...$frames)
    {
        // ty sentry
        $privateField = new ReflectionProperty(Stacktrace::class, 'frames');
        $privateField->setAccessible(true);
        $privateField->setValue($stacktrace, $frames);
        $privateField->setAccessible(false);
    }
}

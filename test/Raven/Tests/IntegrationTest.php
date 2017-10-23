<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class DummyIntegration_Raven_Client extends Raven_Client
{
    private $__sent_events = array();

    public function getSentEvents()
    {
        return $this->__sent_events;
    }
    public function send(&$data)
    {
        if (is_callable($this->send_callback) && call_user_func_array($this->send_callback, array(&$data)) === false) {
            // if send_callback returns falsely, end native send
            return;
        }
        $this->__sent_events[] = $data;
    }
    public static function is_http_request()
    {
        return true;
    }
    // short circuit breadcrumbs
    public function registerDefaultBreadcrumbHandlers()
    {
    }
}

class Raven_Tests_IntegrationTest extends \PHPUnit\Framework\TestCase
{
    private function create_chained_exception()
    {
        try {
            throw new Exception('Foo bar');
        } catch (Exception $ex) {
            try {
                throw new Exception('Child exc', 0, $ex);
            } catch (Exception $ex2) {
                return $ex2;
            }
        }
    }

    public function testCaptureSimpleError()
    {
        $client = new DummyIntegration_Raven_Client('https://public:secret@example.com/1');

        @mkdir('/no/way');

        $client->captureLastError();

        $events = $client->getSentEvents();
        $event = array_pop($events);

        $exc = $event['exception']['values'][0];
        $this->assertEquals($exc['value'], 'mkdir(): No such file or directory');
        $stack = $exc['stacktrace']['frames'];
        $lastFrame = $stack[count($stack) - 1];
        $this->assertEquals(@$lastFrame['filename'], 'test/Raven/Tests/IntegrationTest.php');
    }
}

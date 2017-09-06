<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

use PHPUnit\Framework\TestCase;
use Raven\ClientBuilder;

class DummyIntegration_Raven_Client extends \Raven\Client
{
    private $__sent_events = [];

    public function getSentEvents()
    {
        return $this->__sent_events;
    }

    public function send(&$data)
    {
        if (false === $this->config->shouldCapture($data)) {
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

class IntegrationTest extends TestCase
{
    private function create_chained_exception()
    {
        try {
            throw new \Exception('Foo bar');
        } catch (\Exception $ex) {
            try {
                throw new \Exception('Child exc', 0, $ex);
            } catch (\Exception $ex2) {
                return $ex2;
            }
        }
    }

    public function testCaptureSimpleError()
    {
        $client = ClientBuilder::create([])->getClient();
        $client->store_errors_for_bulk_send = true;

        @mkdir('/no/way');

        $client->captureLastError();

        $event = $client->_pending_events[0]['exception']['values'][0];

        $this->assertEquals($event['value'], 'mkdir(): No such file or directory');
        $this->assertEquals($event['stacktrace']['frames'][count($event['stacktrace']['frames']) - 1]['filename'], 'tests/IntegrationTest.php');
    }
}

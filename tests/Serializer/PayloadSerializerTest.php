<?php

declare(strict_types=1);

namespace Sentry\Tests\Serializer;

use Jean85\PrettyVersions;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Context\OsContext;
use Sentry\Context\RuntimeContext;
use Sentry\Event;
use Sentry\EventId;
use Sentry\ExceptionDataBag;
use Sentry\ExceptionMechanism;
use Sentry\Frame;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TraceId;
use Sentry\UserDataBag;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @group time-sensitive
 */
final class PayloadSerializerTest extends TestCase
{
    /**
     * @var PayloadSerializer
     */
    private $serializer;

    protected function setUp(): void
    {
        $this->serializer = new PayloadSerializer();
    }

    /**
     * @dataProvider serializeDataProvider
     */
    public function testSerialize(Event $event, string $expectedResult, bool $isOutputJson): void
    {
        ClockMock::withClockMock(1597790835);

        $result = $this->serializer->serialize($event);

        if ($isOutputJson) {
            $this->assertJsonStringEqualsJsonString($expectedResult, $result);
        } else {
            $this->assertSame($expectedResult, $result);
        }
    }

    public function serializeDataProvider(): iterable
    {
        ClockMock::withClockMock(1597790835);

        $sdkVersion = PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion();

        yield [
            Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd')),
            <<<JSON
{
    "event_id": "fc9442f5aef34234bb22b9a615e30ccd",
    "timestamp": 1597790835,
    "platform": "php",
    "sdk": {
        "name": "sentry.php",
        "version": "$sdkVersion"
    }
}
JSON
            ,
            true,
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setLevel(Severity::error());
        $event->setLogger('app.php');
        $event->setTransaction('/users/<username>/');
        $event->setServerName('foo.example.com');
        $event->setRelease('721e41770371db95eee98ca2707686226b993eda');
        $event->setEnvironment('production');
        $event->setFingerprint(['myrpc', 'POST', '/foo.bar']);
        $event->setModules(['my.module.name' => '1.0']);
        $event->setStartTimestamp(1597790835);
        $event->setBreadcrumb([
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'log'),
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_NAVIGATION, 'log', null, ['from' => '/login', 'to' => '/dashboard']),
        ]);

        $event->setUser(UserDataBag::createFromArray([
            'id' => 'unique_id',
            'username' => 'my_user',
            'email' => 'foo@example.com',
            'ip_address' => '127.0.0.1',
        ]));

        $event->setTags([
            'ios_version' => '4.0',
            'context' => 'production',
        ]);

        $event->setExtra([
            'my_key' => 1,
            'some_other_value' => 'foo bar',
        ]);

        $event->setRequest([
            'method' => 'POST',
            'url' => 'http://absolute.uri/foo',
            'query_string' => 'query=foobar&page=2',
            'data' => [
                'foo' => 'bar',
            ],
            'cookies' => [
                'PHPSESSID' => '298zf09hf012fh2',
            ],
            'headers' => [
                'content-type' => 'text/html',
            ],
            'env' => [
                'REMOTE_ADDR' => '127.0.0.1',
            ],
        ]);

        $event->setOsContext(new OsContext(
            'Linux',
            '4.19.104-microsoft-standard',
            '#1 SMP Wed Feb 19 06:37:35 UTC 2020',
            'Linux 7944782cd697 4.19.104-microsoft-standard #1 SMP Wed Feb 19 06:37:35 UTC 2020 x86_64'
        ));

        $event->setRuntimeContext(new RuntimeContext(
            'php',
            '7.4.3'
        ));

        $event->setContext('electron', [
            'type' => 'runtime',
            'name' => 'Electron',
            'version' => '4.0',
        ]);

        $frame1 = new Frame(null, 'file/name.py', 3);
        $frame2 = new Frame('myfunction', 'file/name.py', 3, 'raw_function_name', 'absolute/file/name.py', ['my_var' => 'value'], false);
        $frame2->setContextLine('  raise ValueError()');
        $frame2->setPreContext([
            'def foo():',
            '  my_var = \'foo\'',
        ]);

        $frame2->setPostContext([
            '',
            'def main():',
        ]);

        $event->setExceptions([
            new ExceptionDataBag(new \Exception('initial exception')),
            new ExceptionDataBag(
                new \Exception('chained exception'),
                new Stacktrace([
                    $frame1,
                    $frame2,
                ]),
                new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true)
            ),
        ]);

        yield [
            $event,
            <<<JSON
{
    "event_id": "fc9442f5aef34234bb22b9a615e30ccd",
    "timestamp": 1597790835,
    "platform": "php",
    "sdk": {
        "name": "sentry.php",
        "version": "$sdkVersion"
    },
    "start_timestamp": 1597790835,
    "level": "error",
    "logger": "app.php",
    "transaction": "/users/<username>/",
    "server_name": "foo.example.com",
    "release": "721e41770371db95eee98ca2707686226b993eda",
    "environment": "production",
    "fingerprint": [
        "myrpc",
        "POST",
        "/foo.bar"
    ],
    "modules": {
        "my.module.name": "1.0"
    },
    "extra": {
        "my_key": 1,
        "some_other_value": "foo bar"
    },
    "tags": {
        "ios_version": "4.0",
        "context": "production"
    },
    "user": {
        "id": "unique_id",
        "username": "my_user",
        "email": "foo@example.com",
        "ip_address": "127.0.0.1"
    },
    "contexts": {
        "os": {
            "name": "Linux",
            "version": "4.19.104-microsoft-standard",
            "build": "#1 SMP Wed Feb 19 06:37:35 UTC 2020",
            "kernel_version": "Linux 7944782cd697 4.19.104-microsoft-standard #1 SMP Wed Feb 19 06:37:35 UTC 2020 x86_64"
        },
        "runtime": {
            "name": "php",
            "version": "7.4.3"
        },
        "electron": {
            "type": "runtime",
            "name": "Electron",
            "version": "4.0"
        }
    },
    "breadcrumbs": {
        "values": [
            {
                "type": "user",
                "category": "log",
                "level": "info",
                "timestamp": 1597790835
            },
            {
                "type": "navigation",
                "category": "log",
                "level": "info",
                "timestamp": 1597790835,
                "data": {
                    "from": "/login",
                    "to": "/dashboard"
                }
            }
        ]
    },
    "request": {
        "method": "POST",
        "url": "http://absolute.uri/foo",
        "query_string": "query=foobar&page=2",
        "data": {
            "foo": "bar"
        },
        "cookies": {
            "PHPSESSID": "298zf09hf012fh2"
        },
        "headers": {
            "content-type": "text/html"
        },
        "env": {
            "REMOTE_ADDR": "127.0.0.1"
        }
    },
    "exception": {
        "values": [
            {
                "type": "Exception",
                "value": "chained exception",
                "stacktrace": {
                    "frames": [
                        {
                            "filename": "file/name.py",
                            "lineno": 3,
                            "in_app": true
                        },
                        {
                            "filename": "file/name.py",
                            "lineno": 3,
                            "in_app": false,
                            "abs_path": "absolute/file/name.py",
                            "function": "myfunction",
                            "raw_function": "raw_function_name",
                            "pre_context": [
                                "def foo():",
                                "  my_var = 'foo'"
                            ],
                            "context_line": "  raise ValueError()",
                            "post_context": [
                                "",
                                "def main():"
                            ],
                            "vars": {
                                "my_var": "value"
                            }
                        }
                    ]
                },
                "mechanism": {
                    "type": "generic",
                    "handled": true
                }
            },
            {
                "type": "Exception",
                "value": "initial exception"
            }
        ]
    }
}
JSON
            ,
            true,
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setMessage('My raw message with interpreted strings like this', []);

        yield [
            $event,
            <<<JSON
{
    "event_id": "fc9442f5aef34234bb22b9a615e30ccd",
    "timestamp": 1597790835,
    "platform": "php",
    "sdk": {
        "name": "sentry.php",
        "version": "$sdkVersion"
    },
    "message": "My raw message with interpreted strings like this"
}
JSON
            ,
            true,
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setMessage('My raw message with interpreted strings like %s', ['this']);

        yield [
            $event,
            <<<JSON
{
    "event_id": "fc9442f5aef34234bb22b9a615e30ccd",
    "timestamp": 1597790835,
    "platform": "php",
    "sdk": {
        "name": "sentry.php",
        "version": "$sdkVersion"
    },
    "message": {
        "message": "My raw message with interpreted strings like %s",
        "params": ["this"],
        "formatted": "My raw message with interpreted strings like this"
    }
}
JSON
            ,
            true,
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setMessage('My raw message with interpreted strings like %s', ['this'], 'My raw message with interpreted strings like that');

        yield [
            $event,
            <<<JSON
{
    "event_id": "fc9442f5aef34234bb22b9a615e30ccd",
    "timestamp": 1597790835,
    "platform": "php",
    "sdk": {
        "name": "sentry.php",
        "version": "$sdkVersion"
    },
    "message": {
        "message": "My raw message with interpreted strings like %s",
        "params": ["this"],
        "formatted": "My raw message with interpreted strings like that"
    }
}
JSON
            ,
            true,
        ];

        $span1 = new Span();
        $span1->setSpanId(new SpanId('5dd538dc297544cc'));
        $span1->setTraceId(new TraceId('21160e9b836d479f81611368b2aa3d2c'));

        $span2 = new Span();
        $span2->setSpanId(new SpanId('b01b9f6349558cd1'));
        $span2->setParentSpanId(new SpanId('b0e6f15b45c36b12'));
        $span2->setTraceId(new TraceId('1e57b752bc6e4544bbaa246cd1d05dee'));
        $span2->setOp('http');
        $span2->setDescription('GET /sockjs-node/info');
        $span2->setStatus(SpanStatus::ok());
        $span2->setStartTimestamp(1597790835);
        $span2->setTags(['http.status_code' => '200']);
        $span2->setData([
            'url' => 'http://localhost:8080/sockjs-node/info?t=1588601703755',
            'status_code' => 200,
            'type' => 'xhr',
            'method' => 'GET',
        ]);

        $span2->finish(1598659060);

        $event = Event::createTransaction(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setSpans([$span1, $span2]);

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z"}
{"type":"transaction","content_type":"application\/json"}
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"},"spans":[{"span_id":"5dd538dc297544cc","trace_id":"21160e9b836d479f81611368b2aa3d2c","start_timestamp":1597790835},{"span_id":"b01b9f6349558cd1","trace_id":"1e57b752bc6e4544bbaa246cd1d05dee","start_timestamp":1597790835,"parent_span_id":"b0e6f15b45c36b12","timestamp":1598659060,"status":"ok","description":"GET \/sockjs-node\/info","op":"http","data":{"url":"http:\/\/localhost:8080\/sockjs-node\/info?t=1588601703755","status_code":200,"type":"xhr","method":"GET"},"tags":{"http.status_code":"200"}}]}
TEXT
            ,
            false,
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setStacktrace(new Stacktrace([new Frame(null, '', 0)]));

        yield [
            $event,
            <<<JSON
{
    "event_id": "fc9442f5aef34234bb22b9a615e30ccd",
    "timestamp": 1597790835,
    "platform": "php",
    "sdk": {
        "name": "sentry.php",
        "version": "$sdkVersion"
    },
    "stacktrace": {
        "frames": [
            {
                "filename": "",
                "lineno": 0,
                "in_app": true
            }
        ]
    }
}
JSON
            ,
            true,
        ];
    }
}

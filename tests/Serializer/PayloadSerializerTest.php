<?php

declare(strict_types=1);

namespace Sentry\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\CheckIn;
use Sentry\CheckInStatus;
use Sentry\Client;
use Sentry\Context\OsContext;
use Sentry\Context\RuntimeContext;
use Sentry\Event;
use Sentry\EventId;
use Sentry\ExceptionDataBag;
use Sentry\ExceptionMechanism;
use Sentry\Frame;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\Options;
use Sentry\Profiling\Profile;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\TransactionMetadata;
use Sentry\UserDataBag;
use Sentry\Util\SentryUid;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @group time-sensitive
 */
final class PayloadSerializerTest extends TestCase
{
    /**
     * @dataProvider serializeAsEnvelopeDataProvider
     */
    public function testSerializeAsEnvelope(Event $event, string $expectedResult): void
    {
        ClockMock::withClockMock(1597790835);

        $serializer = new PayloadSerializer(new Options([
            'dsn' => 'http://public@example.com/sentry/1',
        ]));

        $result = $serializer->serialize($event);

        $this->assertSame($expectedResult, $result);
    }

    public static function serializeAsEnvelopeDataProvider(): iterable
    {
        ClockMock::withClockMock(1597790835);

        $sdkVersion = Client::SDK_VERSION;

        yield [
            Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd')),
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"event","content_type":"application\/json"}
{"timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
TEXT
            ,
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
            new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT, 'log', null, ['foo', 'bar']),
        ]);

        $event->setUser(UserDataBag::createFromArray([
            'id' => 'unique_id',
            'username' => 'my_user',
            'email' => 'foo@example.com',
            'ip_address' => '127.0.0.1',
            'segment' => 'my_segment',
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
            '7.4.3',
            'cli'
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
                new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true, ['code' => 123])
            ),
        ]);

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"event","content_type":"application\/json"}
{"timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"},"start_timestamp":1597790835,"level":"error","logger":"app.php","transaction":"\/users\/<username>\/","server_name":"foo.example.com","release":"721e41770371db95eee98ca2707686226b993eda","environment":"production","fingerprint":["myrpc","POST","\/foo.bar"],"modules":{"my.module.name":"1.0"},"extra":{"my_key":1,"some_other_value":"foo bar"},"tags":{"ios_version":"4.0","context":"production"},"user":{"id":"unique_id","username":"my_user","email":"foo@example.com","ip_address":"127.0.0.1","segment":"my_segment"},"contexts":{"os":{"name":"Linux","version":"4.19.104-microsoft-standard","build":"#1 SMP Wed Feb 19 06:37:35 UTC 2020","kernel_version":"Linux 7944782cd697 4.19.104-microsoft-standard #1 SMP Wed Feb 19 06:37:35 UTC 2020 x86_64"},"runtime":{"name":"php","sapi":"cli","version":"7.4.3"},"electron":{"type":"runtime","name":"Electron","version":"4.0"}},"breadcrumbs":{"values":[{"type":"user","category":"log","level":"info","timestamp":1597790835},{"type":"navigation","category":"log","level":"info","timestamp":1597790835,"data":{"from":"\/login","to":"\/dashboard"}},{"type":"default","category":"log","level":"info","timestamp":1597790835,"data":{"0":"foo","1":"bar"}}]},"request":{"method":"POST","url":"http:\/\/absolute.uri\/foo","query_string":"query=foobar&page=2","data":{"foo":"bar"},"cookies":{"PHPSESSID":"298zf09hf012fh2"},"headers":{"content-type":"text\/html"},"env":{"REMOTE_ADDR":"127.0.0.1"}},"exception":{"values":[{"type":"Exception","value":"chained exception","stacktrace":{"frames":[{"filename":"file\/name.py","lineno":3,"in_app":true},{"filename":"file\/name.py","lineno":3,"in_app":false,"abs_path":"absolute\/file\/name.py","function":"myfunction","raw_function":"raw_function_name","pre_context":["def foo():","  my_var = 'foo'"],"context_line":"  raise ValueError()","post_context":["","def main():"],"vars":{"my_var":"value"}}]},"mechanism":{"type":"generic","handled":true,"data":{"code":123}}},{"type":"Exception","value":"initial exception"}]}}
TEXT
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setMessage('My raw message with interpreted strings like this', []);

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"event","content_type":"application\/json"}
{"timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"},"message":"My raw message with interpreted strings like this"}
TEXT
            ,
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setMessage('My raw message with interpreted strings like %s', ['this']);

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"event","content_type":"application\/json"}
{"timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"},"message":{"message":"My raw message with interpreted strings like %s","params":["this"],"formatted":"My raw message with interpreted strings like this"}}
TEXT
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setMessage('My raw message with interpreted strings like %s', ['this'], 'My raw message with interpreted strings like that');

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"event","content_type":"application\/json"}
{"timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"},"message":{"message":"My raw message with interpreted strings like %s","params":["this"],"formatted":"My raw message with interpreted strings like that"}}
TEXT
            ,
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
        $event->setRelease('1.0.0');
        $event->setEnvironment('dev');
        $event->setTransaction('GET /');
        $event->setContext('trace', [
            'trace_id' => '21160e9b836d479f81611368b2aa3d2c',
            'span_id' => '5dd538dc297544cc',
        ]);
        $event->setRuntimeContext(new RuntimeContext(
            'php',
            '8.2.3',
            'cli'
        ));
        $event->setOsContext(new OsContext(
            'macOS',
            '13.2.1',
            '22D68',
            'Darwin Kernel Version 22.2.0',
            'aarch64'
        ));

        $excimerLog = [
            [
                'trace' => [
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                ],
                'timestamp' => 0.001,
            ],
            [
                'trace' => [
                    [
                        'file' => '/var/www/html/index.php',
                        'line' => 42,
                    ],
                    [
                        'class' => 'Function',
                        'function' => 'doStuff',
                        'file' => '/var/www/html/function.php',
                        'line' => 84,
                    ],
                ],
                'timestamp' => 0.002,
            ],
        ];

        $profile = new Profile();
        // 2022-02-28T09:41:00Z
        $profile->setStartTimeStamp(1677573660.0000);
        $profile->setExcimerLog($excimerLog);
        $profile->setEventId($event->getId());

        $event->setSdkMetadata('profile', $profile);

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"transaction","content_type":"application\/json"}
{"timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"},"transaction":"GET \/","release":"1.0.0","environment":"dev","contexts":{"os":{"name":"macOS","version":"13.2.1","build":"22D68","kernel_version":"Darwin Kernel Version 22.2.0"},"runtime":{"name":"php","sapi":"cli","version":"8.2.3"},"trace":{"trace_id":"21160e9b836d479f81611368b2aa3d2c","span_id":"5dd538dc297544cc"}},"spans":[{"span_id":"5dd538dc297544cc","trace_id":"21160e9b836d479f81611368b2aa3d2c","start_timestamp":1597790835,"origin":"manual"},{"span_id":"b01b9f6349558cd1","trace_id":"1e57b752bc6e4544bbaa246cd1d05dee","start_timestamp":1597790835,"origin":"manual","parent_span_id":"b0e6f15b45c36b12","timestamp":1598659060,"status":"ok","description":"GET \/sockjs-node\/info","op":"http","data":{"url":"http:\/\/localhost:8080\/sockjs-node\/info?t=1588601703755","status_code":200,"type":"xhr","method":"GET"},"tags":{"http.status_code":"200"}}]}
{"type":"profile","content_type":"application\/json"}
{"device":{"architecture":"aarch64"},"event_id":"fc9442f5aef34234bb22b9a615e30ccd","os":{"name":"macOS","version":"13.2.1","build_number":"22D68"},"platform":"php","release":"1.0.0","environment":"dev","runtime":{"name":"php","sapi":"cli","version":"8.2.3"},"timestamp":"2023-02-28T08:41:00.000+00:00","transaction":{"id":"fc9442f5aef34234bb22b9a615e30ccd","name":"GET \/","trace_id":"21160e9b836d479f81611368b2aa3d2c","active_thread_id":"0"},"version":"1","profile":{"frames":[{"filename":"\/var\/www\/html\/index.php","abs_path":"\/var\/www\/html\/index.php","module":null,"function":"\/var\/www\/html\/index.php","lineno":42},{"filename":"\/var\/www\/html\/function.php","abs_path":"\/var\/www\/html\/function.php","module":"Function","function":"Function::doStuff","lineno":84}],"samples":[{"stack_id":0,"thread_id":"0","elapsed_since_start_ns":1000000},{"stack_id":1,"thread_id":"0","elapsed_since_start_ns":2000000}],"stacks":[[0],[0,1]]}}
TEXT
            ,
        ];

        $event = Event::createTransaction(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setSdkMetadata('dynamic_sampling_context', DynamicSamplingContext::fromHeader('sentry-public_key=public,sentry-trace_id=d49d9bf66f13450b81f65bc51cf49c03,sentry-sample_rate=1'));
        $event->setSdkMetadata('transaction_metadata', new TransactionMetadata());

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"},"trace":{"public_key":"public","trace_id":"d49d9bf66f13450b81f65bc51cf49c03","sample_rate":"1"}}
{"type":"transaction","content_type":"application\/json"}
{"timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"},"spans":[],"transaction_info":{"source":"custom"}}
TEXT
            ,
        ];

        $event = Event::createEvent(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setStacktrace(new Stacktrace([new Frame(null, '', 0)]));

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"event","content_type":"application\/json"}
{"timestamp":1597790835,"platform":"php","sdk":{"name":"sentry.php","version":"$sdkVersion"},"stacktrace":{"frames":[{"filename":"","lineno":0,"in_app":true}]}}
TEXT
            ,
        ];

        $checkinId = SentryUid::generate();
        $checkIn = new CheckIn(
            'my-monitor',
            CheckInStatus::ok(),
            $checkinId,
            '1.0.0',
            'dev',
            10
        );

        $event = Event::createCheckIn(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setCheckIn($checkIn);

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"check_in","content_type":"application\/json"}
{"check_in_id":"$checkinId","monitor_slug":"my-monitor","status":"ok","duration":10,"release":"1.0.0","environment":"dev"}
TEXT
            ,
        ];

        $checkinId = SentryUid::generate();
        $checkIn = new CheckIn(
            'my-monitor',
            CheckInStatus::inProgress(),
            $checkinId
        );

        $event = Event::createCheckIn(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setCheckIn($checkIn);

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"check_in","content_type":"application\/json"}
{"check_in_id":"$checkinId","monitor_slug":"my-monitor","status":"in_progress","duration":null,"release":"","environment":"production"}
TEXT
            ,
        ];

        $checkinId = SentryUid::generate();
        $checkIn = new CheckIn(
            'my-monitor',
            CheckInStatus::ok(),
            $checkinId,
            '1.0.0',
            'dev',
            10,
            new MonitorConfig(
                MonitorSchedule::crontab('0 0 * * *'),
                10,
                12,
                'Europe/Amsterdam',
                5,
                10
            )
        );

        $event = Event::createCheckIn(new EventId('fc9442f5aef34234bb22b9a615e30ccd'));
        $event->setCheckIn($checkIn);
        $event->setContext('trace', [
            'trace_id' => '21160e9b836d479f81611368b2aa3d2c',
            'span_id' => '5dd538dc297544cc',
        ]);

        yield [
            $event,
            <<<TEXT
{"event_id":"fc9442f5aef34234bb22b9a615e30ccd","sent_at":"2020-08-18T22:47:15Z","dsn":"http:\/\/public@example.com\/sentry\/1","sdk":{"name":"sentry.php","version":"$sdkVersion"}}
{"type":"check_in","content_type":"application\/json"}
{"check_in_id":"$checkinId","monitor_slug":"my-monitor","status":"ok","duration":10,"release":"1.0.0","environment":"dev","monitor_config":{"schedule":{"type":"crontab","value":"0 0 * * *","unit":""},"checkin_margin":10,"max_runtime":12,"timezone":"Europe\/Amsterdam","failure_issue_threshold":5,"recovery_threshold":10},"contexts":{"trace":{"trace_id":"21160e9b836d479f81611368b2aa3d2c","span_id":"5dd538dc297544cc"}}}
TEXT
            ,
        ];
    }
}

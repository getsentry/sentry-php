<?php

namespace Raven\Tests;

use Hautelook\Frankenstein\TestCase;
use Raven\DsnParser;

class DsnParserTest extends TestCase
{
    /**
     * @dataProvider getTestParseData
     */
    public function testParse($dsn, $expectedResult)
    {
        $this
            ->array(DsnParser::parse($dsn))
                ->isEqualTo($expectedResult)
        ;
    }

    public function getTestParseData()
    {
        return array(
            array(
                'https://public:secret@example.com/sentry/project-id',
                array(
                    'protocol' => 'https',
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'host' => 'example.com',
                    'port' => null,
                    'path' => '/sentry/',
                    'project_id' => 'project-id',
                )
            ),
            array(
                'https://public:secret@example.com/sentry/project-id/',
                array(
                    'protocol' => 'https',
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'host' => 'example.com',
                    'port' => null,
                    'path' => '/sentry/',
                    'project_id' => 'project-id',
                )
            ),
            array(
                'https://public:secret@example.com:33/sentry/project-id',
                array(
                    'protocol' => 'https',
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'host' => 'example.com',
                    'port' => 33,
                    'path' => '/sentry/',
                    'project_id' => 'project-id',
                )
            ),
            array(
                'https://public:secret@example.com:33/project-id',
                array(
                    'protocol' => 'https',
                    'public_key' => 'public',
                    'secret_key' => 'secret',
                    'host' => 'example.com',
                    'port' => 33,
                    'path' => '/',
                    'project_id' => 'project-id',
                )
            ),
        );
    }

    /**
     * @dataProvider getTestParseInvalidDsnData
     */
    public function testParseInvalidDsn($dsn, $expectedException, $expectedExceptionMessage)
    {
        $this
            ->exception(function () use ($dsn) {
                DsnParser::parse($dsn);
            })
                ->isInstanceOf($expectedException)
                ->hasMessage($expectedExceptionMessage)
        ;
    }

    public function getTestParseInvalidDsnData()
    {
        return array(
            array('', 'InvalidArgumentException', 'The DSN is missing the scheme, user, pass, host part(s).'),
            array('https://example.com/sentry/project-id', 'InvalidArgumentException', 'The DSN is missing the user, pass part(s).'),
            array('https://', 'InvalidArgumentException', 'Malformed DSN "https://".'),
            array('https://public:secret@example.com/sentry/project-id///', 'InvalidArgumentException', 'Invalid DSN path "/sentry/project-id///".'),
        );
    }
}

<p align="center">
    <a href="https://sentry.io" target="_blank" align="center">
        <img src="https://a0wx592cvgzripj.global.ssl.fastly.net/_static/f9c485ccdb254095d3cac55524daba0a/getsentry/images/branding/svg/sentry-horizontal-black.svg" width="280">
    </a>
</p>

# Sentry for PHP

[![Build Status](https://img.shields.io/travis/getsentry/sentry-php.png?style=flat-square)](http://travis-ci.org/getsentry/sentry-php)
[![Total Downloads](https://img.shields.io/packagist/dt/sentry/sentry.svg?style=flat-square)](https://packagist.org/packages/sentry/sentry)
[![Downloads per month](https://img.shields.io/packagist/dm/sentry/sentry.svg?style=flat-square)](https://packagist.org/packages/sentry/sentry)
[![Latest stable version](https://img.shields.io/packagist/v/sentry/sentry.svg?style=flat-square)](https://packagist.org/packages/sentry/sentry)
[![License](http://img.shields.io/packagist/l/sentry/sentry.svg?style=flat-square)](https://packagist.org/packages/sentry/sentry)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/getsentry/sentry-php/master.svg)](https://scrutinizer-ci.com/g/getsentry/sentry-php/)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/getsentry/sentry-php/master.svg)](https://scrutinizer-ci.com/g/getsentry/sentry-php/)

The Sentry PHP error reporter tracks errors and exceptions that happen during the
execution of your application and provides instant notification with detailed
informations needed to prioritize, identify, reproduce and fix each issue. Learn
more about [automatic PHP error reporting with Sentry](https://sentry.io/for/php/).

## Features

- Automatically report (un)handled exceptions and errors
- Send customized diagnostic data
- Process and sanitize data before sending it over the network

## Install

To install the SDK you will need to be using [Composer]([https://getcomposer.org/)
in your project. To install it run:

```bash
curl -sS https://getcomposer.org/installer | php
```

Sentry PHP is not tied to any specific library that sends HTTP messages. Instead,
it uses [Httplug](https://github.com/php-http/httplug) to let users choose whichever
PSR-7 implementation and HTTP client they want to use.

If you just want to get started quickly you should run the following command:

```bash
php composer.phar require sentry/sentry php-http/curl-client guzzlehttp/psr7
```

This will install the library itself along with an HTTP client adapter that uses
cURL as transport method (provided by a Httplug) and a PSR-7 implementation
(provided by Guzzle). You do not have to use those packages if you do not want to.
The SDK does not care about which transport method you want to use because it's
an implementation detail of your application. You may use any package that provides
[`php-http/async-client-implementation`](https://packagist.org/providers/php-http/async-client-implementation)
and [`http-message-implementation`](https://packagist.org/providers/psr/http-message-implementation).

## Usage

```php
require 'vendor/autoload.php';

// Instantiate the SDK with your DSN
$client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])->getClient();

// Capture an exception
$eventId = $client->captureException($ex);

// Give the user feedback
echo 'Sorry, there was an error!';
echo 'Your reference ID is ' . $eventId;
```

For more information, see our [documentation](https://docs.getsentry.com/hosted/clients/php/).


## Integration with frameworks

Other packages exists to integrate this SDK into the most common frameworks.

- [Symfony](https://github.com/getsentry/sentry-symfony)
- [Laravel](https://github.com/getsentry/sentry-laravel)


## Community

- [Documentation](https://docs.getsentry.com/hosted/clients/php/)
- [Bug Tracker](http://github.com/getsentry/sentry-php/issues)
- [Code](http://github.com/getsentry/sentry-php)
- [Mailing List](https://groups.google.com/group/getsentry)
- [IRC](irc://irc.freenode.net/sentry) (irc.freenode.net, #sentry)


Contributing
------------

Dependencies are managed through composer:

```
$ composer install
```

Tests can then be run via phpunit:

```
$ vendor/bin/phpunit
```
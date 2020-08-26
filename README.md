<p align="center">
    <a href="https://sentry.io" target="_blank" align="center">
        <img src="https://sentry-brand.storage.googleapis.com/sentry-logo-black.png" width="280">
    </a>
</p>

# Sentry SDK for PHP

[![Total Downloads](https://poser.pugx.org/sentry/sentry/downloads)](https://packagist.org/packages/sentry/sentry)
[![Monthly Downloads](https://poser.pugx.org/sentry/sentry/d/monthly)](https://packagist.org/packages/sentry/sentry)
[![Latest Stable Version](https://poser.pugx.org/sentry/sentry/v/stable)](https://packagist.org/packages/sentry/sentry)
[![License](https://poser.pugx.org/sentry/sentry/license)](https://packagist.org/packages/sentry/sentry)
[![Discord](https://img.shields.io/discord/621778831602221064)](https://discord.gg/cWnMQeA)

| Version | Build Status | Code Coverage |
|:---------:|:-------------:|:-----:|
| `master`| [![Build Status][Travis Master Build Status Image]][Travis Build Status] [![Build Status][AppVeyor Master Build Status Image]][AppVeyor Build Status] | [![Coverage Status][Master Code Coverage Image]][Master Code Coverage] |
| `develop` | [![Build Status][Travis Develop Build Status Image]][Travis Build Status] [![Build Status][AppVeyor Develop Build Status Image]][AppVeyor Build Status] | [![Coverage Status][Develop Code Coverage Image]][Develop Code Coverage] |

The Sentry PHP error reporter tracks errors and exceptions that happen during the
execution of your application and provides instant notification with detailed
information needed to prioritize, identify, reproduce and fix each issue.

## Install

To install the SDK you will need to be using [Composer]([https://getcomposer.org/)
in your project. To install it please see the [docs](https://getcomposer.org/download/).

This is our "core" SDK, meaning that all the important code regarding error handling lives here.
If you are happy with using the HTTP client we recommend install the SDK like: [`sentry/sdk`](https://github.com/getsentry/sentry-php-sdk)

```bash
composer require sentry/sdk
```

This package (`sentry/sentry`) is not tied to any specific library that sends HTTP messages. Instead,
it uses [Httplug](https://github.com/php-http/httplug) to let users choose whichever
PSR-7 implementation and HTTP client they want to use.

If you just want to get started quickly you should run the following command:

```bash
composer require sentry/sentry php-http/curl-client
```

This is basically what our metapackage (`sentry/sdk`) provides.

This will install the library itself along with an HTTP client adapter that uses
cURL as transport method (provided by Httplug). You do not have to use those
packages if you do not want to. The SDK does not care about which transport method
you want to use because it's an implementation detail of your application. You may
use any package that provides [`php-http/async-client-implementation`](https://packagist.org/providers/php-http/async-client-implementation)
and [`http-message-implementation`](https://packagist.org/providers/psr/http-message-implementation).

## Usage

```php
\Sentry\init(['dsn' => '___PUBLIC_DSN___' ]);

try {
    thisFunctionThrows(); // -> throw new \Exception('foo bar');
} catch (\Exception $exception) {
    \Sentry\captureException($exception);
}
```

### Official integrations

The following integrations are fully supported and maintained by the Sentry team.

- [Symfony](https://github.com/getsentry/sentry-symfony)
- [Laravel](https://github.com/getsentry/sentry-laravel)

### 3rd party integrations

The following integrations are available and maintained by members of the Sentry community.

- [Drupal](https://www.drupal.org/project/raven)
- [Flow Framework](https://github.com/networkteam/Networkteam.SentryClient)
- [OXID eShop](https://github.com/OXIDprojects/sentry)
- [WordPress](https://wordpress.org/plugins/wp-sentry-integration/)
- [ZendFramework](https://github.com/facile-it/sentry-module)
- [SilverStripe](https://github.com/phptek/silverstripe-sentry)
- [TYPO3](https://github.com/networkteam/sentry_client)
- ... feel free to be famous, create a port to your favourite platform!

### 3rd party integrations using old SDK 1.x

- [Neos CMS](https://github.com/networkteam/Netwokteam.Neos.SentryClient)
- [OpenCart](https://github.com/BurdaPraha/oc_sentry)
- [TYPO3](https://github.com/networkteam/sentry_client/tree/2.1.1)

## Community

- [Documentation](https://docs.sentry.io/error-reporting/quickstart/?platform=php)
- [Bug Tracker](http://github.com/getsentry/sentry-php/issues)
- [Code](http://github.com/getsentry/sentry-php)

## Contributing

Dependencies are managed through `composer`:

```
$ composer install
```

Tests can then be run via phpunit:

```
$ vendor/bin/phpunit
```

[Travis Build Status]: http://travis-ci.org/getsentry/sentry-php
[Travis Master Build Status Image]: https://img.shields.io/travis/getsentry/sentry-php/master?logo=travis
[Travis Develop Build Status Image]: https://img.shields.io/travis/getsentry/sentry-php/develop?logo=travis
[AppVeyor Build Status]: https://ci.appveyor.com/project/sentry/sentry-php
[AppVeyor Master Build Status Image]: https://img.shields.io/appveyor/ci/sentry/sentry-php/master?logo=appveyor
[AppVeyor Develop Build Status Image]: https://img.shields.io/appveyor/ci/sentry/sentry-php/develop?logo=appveyor
[Master Code Coverage]: https://codecov.io/gh/getsentry/sentry-php/branch/master
[Master Code Coverage Image]: https://img.shields.io/codecov/c/github/getsentry/sentry-php/master?logo=codecov
[Develop Code Coverage]: https://codecov.io/gh/getsentry/sentry-php/branch/develop
[Develop Code Coverage Image]: https://img.shields.io/codecov/c/github/getsentry/sentry-php/develop?logo=codecov

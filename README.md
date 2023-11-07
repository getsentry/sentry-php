<p align="center">
  <a href="https://sentry.io/?utm_source=github&utm_medium=logo" target="_blank">
    <img src="https://sentry-brand.storage.googleapis.com/sentry-wordmark-dark-280x84.png" alt="Sentry" width="280" height="84">
  </a>
</p>

_Bad software is everywhere, and we're tired of it. Sentry is on a mission to help developers write better software faster, so we can get back to enjoying technology. If you want to join us [<kbd>**Check out our open positions**</kbd>](https://sentry.io/careers/)_

# Official Sentry SDK for PHP

[![CI](https://github.com/getsentry/sentry-php/workflows/CI/badge.svg?branch=master)](https://github.com/getsentry/sentry-php/actions?query=workflow%3ACI+branch%3Amaster)
[![Coverage Status](https://img.shields.io/codecov/c/github/getsentry/sentry-php/master?logo=codecov)](https://codecov.io/gh/getsentry/sentry-php/branch/master)
[![Latest Stable Version](https://poser.pugx.org/sentry/sentry/v/stable)](https://packagist.org/packages/sentry/sentry)
[![License](https://poser.pugx.org/sentry/sentry/license)](https://packagist.org/packages/sentry/sentry)
[![Total Downloads](https://poser.pugx.org/sentry/sentry/downloads)](https://packagist.org/packages/sentry/sentry)
[![Monthly Downloads](https://poser.pugx.org/sentry/sentry/d/monthly)](https://packagist.org/packages/sentry/sentry)
[![Discord](https://img.shields.io/discord/621778831602221064)](https://discord.gg/cWnMQeA)

The Sentry PHP error reporter tracks errors and exceptions that happen during the
execution of your application and provides instant notification with detailed
information needed to prioritize, identify, reproduce and fix each issue.

## Getting started

### Install

Install the SDK using [Composer](https://getcomposer.org/).

```bash
composer require sentry/sentry
```

### Configuration

Initialize the SDK as early as possible in your application.

```php
\Sentry\init(['dsn' => '___PUBLIC_DSN___' ]);
```

### Usage

```php
try {
    thisFunctionThrows(); // -> throw new \Exception('foo bar');
} catch (\Exception $exception) {
    \Sentry\captureException($exception);
}
```

## Official integrations

The following integrations are fully supported and maintained by the Sentry team.

- [Symfony](https://github.com/getsentry/sentry-symfony)
- [Laravel](https://github.com/getsentry/sentry-laravel)

## 3rd party integrations using SDK 4.x

The following integrations are available and maintained by members of the Sentry community.

- [Drupal](https://www.drupal.org/project/raven)
- [WordPress](https://wordpress.org/plugins/wp-sentry-integration/)
- ... feel free to be famous, create a port to your favourite platform!

## 3rd party integrations using the old SDK 3.x

- [Neos Flow](https://github.com/flownative/flow-sentry)
- [ZendFramework](https://github.com/facile-it/sentry-module)
- [Yii2](https://github.com/notamedia/yii2-sentry)
- [Silverstripe](https://github.com/phptek/silverstripe-sentry)
- [CakePHP 3.0 - 4.3](https://github.com/Connehito/cake-sentry)
- [CakePHP 4.4+](https://github.com/lordsimal/cakephp-sentry)
- [October CMS](https://github.com/OFFLINE-GmbH/oc-sentry-plugin)

## 3rd party integrations using the old SDK 2.x

- [Neos Flow](https://github.com/networkteam/Networkteam.SentryClient)
- [OXID eShop](https://github.com/OXIDprojects/sentry)
- [TYPO3](https://github.com/networkteam/sentry_client)
- [CakePHP](https://github.com/Connehito/cake-sentry/tree/3.x)

## 3rd party integrations using the old SDK 1.x

- [Neos CMS](https://github.com/networkteam/Netwokteam.Neos.SentryClient)
- [OpenCart](https://github.com/BurdaPraha/oc_sentry)
- [TYPO3](https://github.com/networkteam/sentry_client/tree/2.1.1)

## Community

- [Documentation](https://docs.sentry.io/error-reporting/quickstart/?platform=php)
- [Bug Tracker](http://github.com/getsentry/sentry-php/issues)
- [Code](http://github.com/getsentry/sentry-php)

## Contributing to the SDK

Please refer to [CONTRIBUTING.md](CONTRIBUTING.md).

## Getting help/support

If you need help setting up or configuring the PHP SDK (or anything else in the Sentry universe) please head over to the [Sentry Community on Discord](https://discord.com/invite/sentry). There is a ton of great people in our Discord community ready to help you!

## Resources

- [![Documentation](https://img.shields.io/badge/documentation-sentry.io-green.svg)](https://docs.sentry.io/quickstart/)
- [![Discord](https://img.shields.io/discord/621778831602221064)](https://discord.gg/Ww9hbqr)
- [![Stack Overflow](https://img.shields.io/badge/stack%20overflow-sentry-green.svg)](http://stackoverflow.com/questions/tagged/sentry)
- [![Twitter Follow](https://img.shields.io/twitter/follow/getsentry?label=getsentry&style=social)](https://twitter.com/intent/follow?screen_name=getsentry)

## License

Licensed under the MIT license, see [`LICENSE`](LICENSE)

<p align="center">
    <a href="https://sentry.io" target="_blank" align="center">
        <img src="https://sentry-brand.storage.googleapis.com/sentry-logo-black.png" width="280">
    </a>
</p>

# Sentry for PHP

[![Build Status](https://secure.travis-ci.org/getsentry/sentry-php.png?branch=master)](http://travis-ci.org/getsentry/sentry-php)
[![Total Downloads](https://poser.pugx.org/sentry/sentry/downloads)](https://packagist.org/packages/sentry/sentry)
[![Monthly Downloads](https://poser.pugx.org/sentry/sentry/d/monthly)](https://packagist.org/packages/sentry/sentry)
[![Latest Stable Version](https://poser.pugx.org/sentry/sentry/v/stable)](https://packagist.org/packages/sentry/sentry)
[![License](https://poser.pugx.org/sentry/sentry/license)](https://packagist.org/packages/sentry/sentry)
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
in your project. To install it please see the [docs](https://getcomposer.org/download/).

Sentry PHP is not tied to any specific library that sends HTTP messages. Instead,
it uses [Httplug](https://github.com/php-http/httplug) to let users choose whichever
PSR-7 implementation and HTTP client they want to use.

If you just want to get started quickly you should run the following command:

```bash
php composer.phar require sentry/sentry php-http/curl-client guzzlehttp/psr7
```

This will install the library itself along with an HTTP client adapter that uses
cURL as transport method (provided by Httplug) and a PSR-7 implementation
(provided by Guzzle). You do not have to use those packages if you do not want to.
The SDK does not care about which transport method you want to use because it's
an implementation detail of your application. You may use any package that provides
[`php-http/async-client-implementation`](https://packagist.org/providers/php-http/async-client-implementation)
and [`http-message-implementation`](https://packagist.org/providers/psr/http-message-implementation).

## Usage

```php
namespace XXX;

use Sentry\ClientBuilder;

require 'vendor/autoload.php';

// Instantiate the SDK with your DSN
$client = ClientBuilder::create(['server' => 'http://public@example.com/1'])->getClient();

// Capture an exception
$eventId = $client->captureException(new \RuntimeException('Hello World!'));

// Give the user feedback
echo 'Sorry, there was an error!';
echo 'Your reference ID is ' . $eventId;
```

For more information, see our [documentation](https://docs.getsentry.com/hosted/clients/php/).


## Integration with frameworks

Other packages exists to integrate this SDK into the most common frameworks.

### Official integrations

The following integrations are fully supported and maintained by the Sentry team.

- [Symfony](https://github.com/getsentry/sentry-symfony)
- [Laravel](https://github.com/getsentry/sentry-laravel)

### 3rd party integrations

The following integrations are available and maintained by members of the Sentry community.

- [Nette](https://github.com/Salamek/raven-nette)
- [ZendFramework](https://github.com/facile-it/sentry-module)
- [WordPress](https://wordpress.org/plugins/wp-sentry-integration/)
- [Drupal](https://www.drupal.org/project/raven)
- [OpenCart](https://github.com/BurdaPraha/oc_sentry)
- ... feel free to be famous, create a port to your favourite platform!

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


Tagging a Release
-----------------

1. Make sure ``CHANGES`` is up to date (add the release date) and ``master`` is green.

2. Create a new branch for the minor version (if not present):

```
$ git checkout -b releases/2.1.x
```

3. Update the hardcoded version tag in ``Client.php``:

```php
namespace Sentry;

class Client
{
    const VERSION = '2.1.0';
}
```

4. Commit the change:

```
$ git commit -a -m "2.1.0"
```

5. Tag the branch:

```
git tag 2.1.0
```

6. Push the tag:

```
git push --tags
```

7. Switch back to ``master``:

```
git checkout master
```

8. Add the next minor release to the ``CHANGES`` file:

```
## 2.1.0 (unreleased)
```

9. Update the version in ``Client.php``:

```php
namespace Sentry;

class Client implements ClientInterface
{
    const VERSION = '2.1.x-dev';
}
```

10. Lastly, update the composer version in ``composer.json``:

```json
    "extra": {
        "branch-alias": {
            "dev-master": "2.1.x-dev"
        }
    }
```

All done! Composer will pick up the tag and configuration automatically.

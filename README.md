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

## Usage

```php
// Instantiate a new client with a compatible DSN and install built-in
// handlers
$client = (new Raven_Client('http://public@example.com/1'))->install();

// Capture an exception
$event_id = $client->captureException($ex);

// Give the user feedback
echo "Sorry, there was an error!";
echo "Your reference ID is " . $event_id;
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
$ git checkout -b releases/1.10.x
```

3. Update the hardcoded version tag in ``Client.php``:

```php
class Raven_Client
{
    const VERSION = '1.10.0';
}
```

4. Commit the change:

```
$ git commit -a -m "1.10.0"
```

5. Tag the branch:

```
git tag 1.10.0
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
## 1.11.0 (unreleased)
```

9. Update the version in ``Client.php``:

```php
class Raven_Client
{
    const VERSION = '1.11.x-dev';
}
```

10. Lastly, update the composer version in ``composer.json``:

```json
    "extra": {
        "branch-alias": {
            "dev-master": "1.11.x-dev"
        }
    }
```

All done! Composer will pick up the tag and configuration automatically.

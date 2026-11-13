<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Sentry\Options;

final class HttpUrlCollector
{
    private function __construct()
    {
    }

    /**
     * @param UriInterface|string $url
     *
     * @return string|null `null` if the URL is not collected or cannot be parsed
     */
    public static function collect(Options $options, HttpMessageType $type, $url): ?string
    {
        if ($url instanceof UriInterface) {
            $uri = $url;
            $queryString = $url->getQuery();
            $fragment = $url->getFragment();
        } else {
            try {
                $uri = new Uri($url);
            } catch (\InvalidArgumentException $exception) {
                return null;
            }

            // Take the query string and fragment as they appear in the URL, the parsed URI encodes them
            $queryString = (string) parse_url($url, \PHP_URL_QUERY);
            $fragment = (string) parse_url($url, \PHP_URL_FRAGMENT);
        }

        $userInfo = $uri->getUserInfo();
        $authority = $uri->withUserInfo('')->getAuthority();

        if ($userInfo !== '') {
            $authority = (strpos($userInfo, ':') === false ? '[Filtered]' : '[Filtered]:[Filtered]') . '@' . $authority;
        }

        return Uri::composeComponents(
            $uri->getScheme(),
            $authority,
            $uri->getPath(),
            self::collectQueryString($options, $queryString),
            $fragment
        );
    }

    /**
     * @return string|null `null` if the query string is empty or not collected
     */
    public static function collectQueryString(Options $options, string $queryString): ?string
    {
        if ($queryString === '') {
            return null;
        }

        return (new KeyValueDataFilter($options->getDataCollection()->getUrlQueryParams()))->filterQueryString($queryString);
    }
}

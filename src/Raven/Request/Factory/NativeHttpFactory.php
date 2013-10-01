<?php

namespace Raven\Request\Factory;

use Raven\Request\Interfaces\Http;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 *
 * Code from the original raven-php client
 */
class NativeHttpFactory implements HttpFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create()
    {
        if (php_sapi_name() === 'cli') {
            return null;
        }

        $env = $headers = array();

        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                if (in_array($key, array('HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH'))) {
                    continue;
                }
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
            } elseif (in_array($key, array('CONTENT_TYPE', 'CONTENT_LENGTH'))) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))))] = $value;
            } else {
                $env[$key] = $value;
            }
        }

        $http = new Http(
            $this->getCurrentUrl(),
            $_REQUEST['REQUEST_METHOD']
        );

        if (isset($_REQUEST['QUERY_STRING'])) {
            $http->setQueryString($_REQUEST['QUERY_STRING']);
        }

        if (!empty($_POST)) {
            $http->setData($_POST);
        }
        if (!empty($_COOKIE)) {
            $http->setCookies($_COOKIE);
        }
        if (!empty($headers)) {
            $http->setHeaders($headers);
        }
        if (!empty($env)) {
            $http->setEnv($env);
        }

        return $http;
    }

    private function getCurrentUrl()
    {
        $schema =
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443
                ? "https://"
                : "http://"
        ;

        return $schema . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}

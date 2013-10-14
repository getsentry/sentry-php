<?php

namespace Raven\Request\Factory;

use Raven\Request\Interfaces\Http;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Adrien Brault <adrien.brault@gmail.com>
 */
class SymfonyHttpFactory implements HttpFactoryInterface
{
    /**
     * @var Request
     */
    private $request;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * {@inheritdoc}
     */
    public function create()
    {
        if (null === $this->request) {
            return null;
        }

        $http = new Http(
            $this->request->getUriForPath($this->request->getPathInfo()),
            $this->request->getMethod()
        );

        $queryString = $this->request->getQueryString();
        if (strlen($queryString) > 0) {
            $http->setQueryString($queryString);
        }
        $http->setData($this->request->request->all());
        $http->setCookies($this->request->cookies->all());
        $http->setHeaders(array_map(function (array $values) {
            return count($values) === 1 ? reset($values) : $values;
        }, $this->request->headers->all()));
        $http->setEnv($this->request->server->all());

        return $http;
    }
}

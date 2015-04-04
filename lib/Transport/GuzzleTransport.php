<?php namespace Raven\Transport;

use Guzzle\Http\ClientInterface;
use Guzzle\Http\Exception\MultiTransferException;
use Raven\Contracts\Request as RequestContract;
use Raven\Contracts\Transport;

class GuzzleTransport implements Transport
{

    /**
     * Request queue
     *
     * @var \Guzzle\Http\Message\EntityEnclosingRequestInterface[]
     */
    protected $queue = array();

    /**
     * HTTP client for dealing with requests
     *
     * @var \Guzzle\Http\ClientInterface
     */
    protected $client;

    /**
     * Make a new transport
     *
     * @param \Guzzle\Http\ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Queue a request ready to send
     *
     * @param \Raven\Contracts\Request $request
     * @return $this
     */
    public function queue(RequestContract $request)
    {
        $newRequest = $this->client->post(
            $request->getUrl(), $request->getHeaders(), $request->getBody()
        );

        array_push($this->queue, $newRequest);

        return $this;
    }

    /**
     * Immediately send all queued requests
     *
     * @param bool $async
     * @return $this
     */
    public function send($async = true)
    {
        // Guzzle 3 ignores async; use Guzzle 5 for that.
        try
        {
            $this->client->send($this->queue);
        }
        catch (MultiTransferException $exceptions)
        {
            foreach ($exceptions as $exception)
            {
                // TODO: handle exceptions gracefully
                throw $exception;
            }
        }

        return $this;
    }
}
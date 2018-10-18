<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Breadcrumbs\Recorder as BreadcrumbRecorder;
use Sentry\Context\Context;
use Sentry\Context\RuntimeContext;
use Sentry\Context\ServerOsContext;
use Sentry\Context\TagsContext;

/**
 * This interface must be implemented by all Raven client classes.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface ClientInterface
{
    /**
     * Gets the configuration of the client.
     *
     * @return Configuration
     */
    public function getConfig();

    /**
     * Gets the transaction stack.
     *
     * @return TransactionStack
     */
    public function getTransactionStack();

    /**
     * Adds a new middleware with the given priority to the stack.
     *
     * @param callable $middleware The middleware instance
     * @param int      $priority   The priority. The higher this value, the
     *                             earlier a processor will be executed in
     *                             the chain (defaults to 0)
     */
    public function addMiddleware(callable $middleware, $priority = 0);

    /**
     * Removes the given middleware from the stack.
     *
     * @param callable $middleware The middleware instance
     */
    public function removeMiddleware(callable $middleware);

    /**
     * Gets the breadcrumbs recorder.
     *
     * @return BreadcrumbRecorder
     */
    public function getBreadcrumbsRecorder();

    /**
     * Records the given breadcrumb.
     *
     * @param Breadcrumb $breadcrumb The breadcrumb instance
     */
    public function leaveBreadcrumb(Breadcrumb $breadcrumb);

    /**
     * Clears all recorded breadcrumbs.
     */
    public function clearBreadcrumbs();

    /**
     * Logs a message.
     *
     * @param string $message The message (primary description) for the event
     * @param array  $params  Params to use when formatting the message
     * @param array  $payload Additional attributes to pass with this event
     *
     * @return string
     */
    public function captureMessage(string $message, array $params = [], array $payload = []);

    /**
     * Logs an exception.
     *
     * @param \Throwable $exception The exception object
     * @param array      $payload   Additional attributes to pass with this event
     *
     * @return string
     */
    public function captureException(\Throwable $exception, array $payload = []);

    /**
     * Logs the most recent error (obtained with {@link error_get_last}).
     *
     * @param array $payload Additional attributes to pass with this event
     *
     * @return string|null
     */
    public function captureLastError(array $payload = []);

    /**
     * Gets the last event that was captured by the client. However, it could
     * have been sent or still sit in the queue of pending events.
     *
     * @return Event
     */
    public function getLastEvent();

    /**
     * Return the last captured event's ID or null if none available.
     *
     * @return string|null
     *
     * @deprecated since version 2.0, to be removed in 3.0. Use getLastEvent() instead.
     */
    public function getLastEventId();

    /**
     * Captures a new event using the provided data.
     *
     * @param array $payload The data of the event being captured
     *
     * @return string
     */
    public function capture(array $payload);

    /**
     * Sends the given event to the Sentry server.
     *
     * @param Event $event The event to send
     */
    public function send(Event $event);

    /**
     * Translate a PHP Error constant into a Sentry log level group.
     *
     * @param int $severity PHP E_$x error constant
     *
     * @return string Sentry log level group
     */
    public function translateSeverity(int $severity);

    /**
     * Provide a map of PHP Error constants to Sentry logging groups to use instead
     * of the defaults in translateSeverity().
     *
     * @param string[] $map
     */
    public function registerSeverityMap($map);

    /**
     * Gets the user context.
     *
     * @return Context
     */
    public function getUserContext();

    /**
     * Gets the tags context.
     *
     * @return TagsContext
     */
    public function getTagsContext();

    /**
     * Gets the extra context.
     *
     * @return Context
     */
    public function getExtraContext();

    /**
     * Gets the runtime context.
     *
     * @return RuntimeContext
     */
    public function getRuntimeContext();

    /**
     * Gets the server OS context.
     *
     * @return ServerOsContext
     */
    public function getServerOsContext();

    /**
     * Sets whether all the objects should be serialized by the representation
     * serializer.
     *
     * @param bool $value Whether the serialization of all objects is enabled or not
     */
    public function setAllObjectSerialize($value);

    /**
     * Gets the representation serialier.
     *
     * @return ReprSerializer
     */
    public function getRepresentationSerializer();

    /**
     * Sets the representation serializer.
     *
     * @param ReprSerializer $representationSerializer The serializer instance
     */
    public function setRepresentationSerializer(ReprSerializer $representationSerializer);

    /**
     * Gets the serializer.
     *
     * @return Serializer
     */
    public function getSerializer();

    /**
     * Sets the serializer.
     *
     * @param Serializer $serializer The serializer instance
     */
    public function setSerializer(Serializer $serializer);
}

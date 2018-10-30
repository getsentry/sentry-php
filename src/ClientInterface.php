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
use Sentry\State\Scope;

/**
 * This interface must be implemented by all Raven client classes.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface ClientInterface
{
    /**
     * Returns the options of the client.
     *
     * @return Options
     */
    public function getOptions(): Options;

    /**
     * Records the given breadcrumb.
     *
     * @param Breadcrumb $breadcrumb The breadcrumb instance
     * @param Scope|null $scope      An optional scope to store this breadcrumb in
     */
    public function addBreadcrumb(Breadcrumb $breadcrumb, ?Scope $scope = null): void;

    /**
     * Logs a message.
     *
     * @param string     $message The message (primary description) for the event
     * @param Severity   $level   The level of the message to be sent
     * @param Scope|null $scope   An optional scope keeping the state
     *
     * @return null|string
     */
    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null): ?string;

    /**
     * Logs an exception.
     *
     * @param \Throwable $exception The exception object
     * @param Scope|null $scope     An optional scope keeping the state
     *
     * @return null|string
     */
    public function captureException(\Throwable $exception, ?Scope $scope = null): ?string;

    /**
     * Captures a new event using the provided data.
     *
     * @param array      $payload The data of the event being captured
     * @param Scope|null $scope   An optional scope keeping the state
     *
     * @return null|string
     */
    public function captureEvent(array $payload, ?Scope $scope = null): ?string;

    /**
     * Sends the given event to the Sentry server.
     *
     * @param Event $event The event to send
     *
     * @return null|string
     */
    public function send(Event $event): ?string;

    /**
     * Gets the transaction stack.
     *
     * @return TransactionStack
     */
    public function getTransactionStack();


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

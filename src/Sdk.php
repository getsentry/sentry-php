<?php

namespace Sentry;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\State\Hub;

function init(array $options = []): void
{
    Hub::setCurrent(new Hub(ClientBuilder::create($options)->getClient()));
}

/**
 * Capture a message and send it to Sentry.
 *
 * @param string $message
 *
 * @return string
 */
function captureMessage(string $message): ?string
{
    return Hub::getCurrent()->captureMessage($message);
}

/**
 * Capture a \Throwable and send it to Sentry.
 *
 * @param \Throwable $exception
 *
 * @return string
 */
function captureException($exception): ?string
{
    return Hub::getCurrent()->captureException($exception);
}

// TODO
///**
// * Captures and event and send it to Sentry.
// *
// * @param Event $event
// *
// * @return null|string
// */
//function captureEvent(Event $event): ?string
//{
//    return Hub::getCurrent()->captureEvent($event);
//}

/**
 * Add a breadcrumb which will be send with the next event.
 *
 * @param Breadcrumb $breadcrumb
 */
function addBreadcrumb(Breadcrumb $breadcrumb): void
{
    Hub::getCurrent()->addBreadcrumb($breadcrumb);
}

/**
 * Configure the current scope to send context information with the event.
 *
 * @param \Closure $callback
 */
function configureScope(\Closure $callback): void
{
    Hub::getCurrent()->configureScope($callback);
}

/**
 * Pushes and Pops a Scope. Use this to have an isolated state before sending a event.
 *
 * @param \Closure $callback
 */
function withScope(\Closure $callback): void
{
    Hub::getCurrent()->withScope($callback);
}

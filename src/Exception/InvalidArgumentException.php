<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Exception;

/**
 * This class represents an exception thrown if an argument does not match with
 * the expected value.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}

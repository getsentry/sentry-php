<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

@trigger_error('The '.__NAMESPACE__.'\Raven_SanitizeDataProcessor class is deprecated since version 1.7 and will be removed in 2.0. Use the Raven_Processor_SanitizeDataProcessor class in the same namespace instead.', E_USER_DEPRECATED);

/**
 * {@inheritdoc}
 */
class Raven_SanitizeDataProcessor extends Raven_Processor_SanitizeDataProcessor
{
}

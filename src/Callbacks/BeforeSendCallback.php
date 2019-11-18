<?php

namespace Sentry\Callbacks;

use Sentry\Event;

interface BeforeSendCallback
{
	public function __invoke(Event $event);
}

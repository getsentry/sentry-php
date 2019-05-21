<?php

namespace Sentry\Tests\Fixtures\code;

class BrokenClass
{
    public function brokenMethod()
    {
        echo Class();
    }
}

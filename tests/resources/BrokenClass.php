<?php

namespace Sentry\Tests\resources;

class BrokenClass
{
    public function brokenMethod()
    {
        echo Class();
    }
}

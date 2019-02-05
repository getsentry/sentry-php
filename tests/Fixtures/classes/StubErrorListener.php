<?php

namespace Sentry\Tests\Fixtures\classes;

use Sentry\ErrorListenerInterface;

final class StubErrorListener implements ErrorListenerInterface
{
    /** 
     * @var \ErrorException|null 
     */
    private $error;
    
    /** 
     * @var callable|null 
     */
    private $callable;

    public function __construct(callable $callable = null)
    {
        $this->callable = $callable;
    }

    public function __invoke(\ErrorException $error): void
    {
        $this->error = $error;
        
        if ($this->callable) {
            call_user_func($this->callable, $error);
        }
    }

    public function getError(): ?\ErrorException
    {
        return $this->error;
    }
}

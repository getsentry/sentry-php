<?php

declare(strict_types=1);

namespace Sentry\State;

/**
 * The scope holds data that should implicitly be sent with Sentry events. It
 * can hold context data, extra parameters, level overrides, fingerprints etc.
 */
class GlobalScope extends MutableScope
{
    public function __construct()
    {
        parent::__construct();
    }

    public function merge(IsolationScope $scope): MergedScope
    {
        return new MergedScope($this->scopeData->merge($scope->scopeData), $scope->getSpan());
    }
}

<?php

declare(strict_types=1);

use Http\Discovery\ClassDiscovery;
use Sentry\Tests\MockClientStrategy;

error_reporting(E_ALL | E_STRICT);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

ClassDiscovery::appendStrategy(MockClientStrategy::class);

<?php

declare(strict_types=1);

use Http\Discovery\ClassDiscovery;
use Http\Discovery\Strategy\MockClientStrategy;

require_once __DIR__ . '/../vendor/autoload.php';

ClassDiscovery::appendStrategy(MockClientStrategy::class);

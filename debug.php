<?php

use Symfony\Component\Debug\Exception\FlattenException;

require __DIR__ . '/vendor/autoload.php';

try {
    try {
        $client = Raven\Client::create(array(
            'dsn' => 'http://',
        ));
    } catch (\Exception $e) {
        throw new \RuntimeException('oh my', 0, $e);
    }
} catch (\Exception $e) {
    var_dump(FlattenException::create($e));
}

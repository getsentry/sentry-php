#!/usr/bin/php
<?php
echo "init...\n";
require_once __DIR__.'/../../vendor/autoload.php';
$cli_param = getopt('', ['config:',]);

/** @var \NokitaKaze\TestHTTPServer\ServerSettings $options */
$options = unserialize(file_get_contents($cli_param['config']));

$options->onRequest = function ($server, $connect) {
    /** @var \NokitaKaze\TestHTTPServer\Server $server */
    /** @var \NokitaKaze\TestHTTPServer\ClientDatum $connect */
    $output_filename = $server->get_option('output_filename');

    $connect->server = null;
    $saved = file_put_contents(
        $output_filename, serialize(
        [
            'connection' => $connect,
        ]
    ), LOCK_EX
    );
    if ($saved === false) {
        $server->answer($connect, 500, 'OK', '');
        $server->close_connection($connect);
        echo "can not save content\n";
        exit(1);
    }
    if ($server->get_option('http_code') == 403) {
        $server->answer($connect, 403, 'Denied', json_encode(['error' => 'Denied']));
    } else {
        $server->answer($connect, 200, 'OK', json_encode(['event' => uniqid()]));
    }

    $server->close_connection($connect);
    echo "done\n";
    exit(0);
};
$server = new \NokitaKaze\TestHTTPServer\Server($options);

$server->init_listening();
echo "listen...\n";
$server->listen(time() + 60);

echo "No connection\n";
exit(1);
?>
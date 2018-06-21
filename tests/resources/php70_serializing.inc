<?php
/**
 * @var array[] $data
 */

/**
 * @param int $param1_70a
 * @throws Exception
 */
$closure_701 = function (int $param1_70a) {
    throw new \Exception('Don\'t even think about invoke me');
};

$closure_702 = function (&$param): int {
    return (int)$param;
};

$data = array_merge(
    $data, [
        [
            'callable' => $closure_701,
            'expected' => 'Lambda {closure} [int param1_70a]',
        ], [
            'callable' => $closure_702,
            'expected' => 'Lambda int {closure} [mixed|null &param]',
        ],
    ]
);

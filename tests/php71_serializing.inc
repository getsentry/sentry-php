<?php
/**
 * @var array[] $data
 */

/**
 * @param int $param
 * @return int|null
 * @throws Exception
 */
$closure_711 = function (int $param): ?int {
    throw new \Exception('Don\'t even think about invoke me');
};

$closure_712 = function (?int $param1_70b) {
    throw new \Exception('Don\'t even think about invoke me');
};

$closure_713 = function (?int $param1_70c): void {
    throw new \Exception('Don\'t even think about invoke me');
};

$data = array_merge(
    $data, [
        [
            'callable' => $closure_711,
            'expected' => 'Lambda int {closure} [int param]',
        ], [
            'callable' => $closure_712,
            'expected' => 'Lambda {closure} [int|null param1_70b]',
        ], [
            'callable' => $closure_713,
            'expected' => 'Lambda void {closure} [int|null param1_70c]',
        ]
    ]
);
?>